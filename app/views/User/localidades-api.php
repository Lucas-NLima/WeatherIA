<?php
header('Content-Type: application/json; charset=utf-8');

function responderJson($payload, $codigoHttp = 200) {
    http_response_code($codigoHttp);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function chamarApiJson($url, $metodo = 'GET', $payload = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);

    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        responderJson(['erro' => 'Erro ao conectar com a base de localidades.', 'detalhes' => $curlError], 500);
    }

    $dados = json_decode($response, true);
    if (!is_array($dados)) {
        responderJson(['erro' => 'Resposta inválida da base de localidades.'], 500);
    }

    return ['httpCode' => $httpCode, 'dados' => $dados];
}

function traduzirContinente($region, $subregion) {
    if ($region === 'Africa') return 'África';
    if ($region === 'Asia') return 'Ásia';
    if ($region === 'Europe') return 'Europa';
    if ($region === 'Oceania') return 'Oceania';
    if ($region === 'Antarctic') return 'Antártida';

    if ($region === 'Americas') {
        if ($subregion === 'South America') return 'América do Sul';
        if ($subregion === 'Central America' || $subregion === 'Caribbean') return 'América Central';
        return 'América do Norte';
    }

    return 'Outros';
}

function carregarPaises() {
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clima-localidades-paises-v2.json';
    $cacheValido = file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400);

    if ($cacheValido) {
        header('Content-Type: application/json; charset=utf-8');
        readfile($cacheFile);
        exit;
    }

    $statesResult = chamarApiJson('https://countriesnow.space/api/v0.1/countries/states');
    if ($statesResult['httpCode'] >= 400 || !empty($statesResult['dados']['error'])) {
        responderJson(['erro' => 'Não foi possível carregar países e estados.'], 502);
    }

    $restResult = chamarApiJson('https://restcountries.com/v3.1/all?fields=name,cca2,region,subregion,translations');
    if ($restResult['httpCode'] >= 400) {
        responderJson(['erro' => 'Não foi possível carregar continentes dos países.'], 502);
    }

    $restPorCodigo = [];
    foreach ($restResult['dados'] as $paisRest) {
        if (!empty($paisRest['cca2'])) {
            $restPorCodigo[strtoupper($paisRest['cca2'])] = $paisRest;
        }
    }

    $paises = [];
    foreach ($statesResult['dados']['data'] as $pais) {
        $codigo = strtoupper($pais['iso2'] ?? '');
        if ($codigo === '') continue;

        $rest = $restPorCodigo[$codigo] ?? null;
        $nomePortugues = $pais['name'];
        $continente = 'Outros';

        if ($rest) {
            $nomePortugues = $rest['translations']['por']['common'] ?? $rest['name']['common'] ?? $pais['name'];
            $continente = traduzirContinente($rest['region'] ?? '', $rest['subregion'] ?? '');
        }

        $estados = [];
        foreach (($pais['states'] ?? []) as $estado) {
            if (empty($estado['name'])) continue;
            $estados[] = [
                'nome' => $estado['name'],
                'codigo' => $estado['state_code'] ?? ''
            ];
        }

        usort($estados, function ($a, $b) {
            return strcasecmp($a['nome'], $b['nome']);
        });

        $paises[] = [
            'nome' => $nomePortugues,
            'nome_api' => $pais['name'],
            'codigo' => $codigo,
            'continente' => $continente,
            'estados' => $estados
        ];
    }

    usort($paises, function ($a, $b) {
        return strcasecmp($a['nome'], $b['nome']);
    });

    $continentes = array_values(array_unique(array_filter(array_column($paises, 'continente'))));
    sort($continentes);

    $payload = [
        'paises' => $paises,
        'continentes' => $continentes,
        'total' => count($paises)
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($cacheFile, $json);

    echo $json;
    exit;
}

function carregarCidades() {
    $paisApi = isset($_GET['pais_api']) ? trim($_GET['pais_api']) : '';
    $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

    if ($paisApi === '') {
        responderJson(['erro' => 'País não informado para carregar cidades.'], 400);
    }

    if ($estado !== '') {
        $url = 'https://countriesnow.space/api/v0.1/countries/state/cities';
        $body = ['country' => $paisApi, 'state' => $estado];
    } else {
        $url = 'https://countriesnow.space/api/v0.1/countries/cities';
        $body = ['country' => $paisApi];
    }

    $result = chamarApiJson($url, 'POST', $body);
    if ($result['httpCode'] >= 400 || !empty($result['dados']['error'])) {
        responderJson(['erro' => 'Cidades não encontradas para a seleção atual.'], 404);
    }

    $cidades = [];
    foreach (($result['dados']['data'] ?? []) as $cidade) {
        if (is_string($cidade) && trim($cidade) !== '') {
            $cidades[] = trim($cidade);
        }
    }

    $cidades = array_values(array_unique($cidades));
    sort($cidades, SORT_NATURAL | SORT_FLAG_CASE);

    responderJson([
        'cidades' => $cidades,
        'total' => count($cidades)
    ]);
}

$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'paises';

if ($tipo === 'paises') {
    carregarPaises();
}

if ($tipo === 'cidades') {
    carregarCidades();
}

responderJson(['erro' => 'Tipo de consulta inválido.'], 400);
