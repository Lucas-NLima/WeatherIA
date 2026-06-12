<?php
// clima-api.php

// Define que o retorno será em formato JSON
header('Content-Type: application/json; charset=utf-8');

// Verifica se o parâmetro 'cidade' foi enviado via GET
if (!isset($_GET['cidade']) || empty(trim($_GET['cidade']))) {
    echo json_encode(['erro' => 'Nome da cidade não fornecido.']);
    exit;
}

$cidade = trim($_GET['cidade']);
$pais = isset($_GET['pais']) ? strtoupper(trim($_GET['pais'])) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$continente = isset($_GET['continente']) ? trim($_GET['continente']) : '';

// A chave da API fornecida
$apiKey = "2b949b5e5aaa2872144e2fd3bdfd5370";

function responderErro($mensagem, $codigoHttp = 400, $detalhes = null) {
    http_response_code($codigoHttp);
    $payload = ['erro' => $mensagem];
    if ($detalhes) {
        $payload['detalhes'] = $detalhes;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function chamarApi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'curlError' => $curlError
    ];
}

function mensagemErroOpenWeather($response, $httpCode) {
    $errorData = json_decode($response, true);
    $mensagem = isset($errorData['message']) ? $errorData['message'] : 'Erro desconhecido da API.';

    if ($httpCode == 404 && $mensagem == 'city not found') {
        return 'Cidade não encontrada. Use os filtros para diferenciar locais com nomes iguais.';
    }

    return $mensagem;
}

function continenteDoPais($codigoPais) {
    $continentes = [
        'África' => ['AO', 'BF', 'BI', 'BJ', 'BW', 'CD', 'CF', 'CG', 'CI', 'CM', 'CV', 'DJ', 'DZ', 'EG', 'ER', 'ET', 'GA', 'GH', 'GM', 'GN', 'GQ', 'GW', 'KE', 'KM', 'LR', 'LS', 'LY', 'MA', 'MG', 'ML', 'MR', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NG', 'RE', 'RW', 'SC', 'SD', 'SL', 'SN', 'SO', 'ST', 'SZ', 'TD', 'TG', 'TN', 'TZ', 'UG', 'ZA', 'ZM', 'ZW'],
        'América do Norte' => ['BM', 'CA', 'GL', 'MX', 'PM', 'US'],
        'América Central' => ['AG', 'AI', 'AW', 'BB', 'BL', 'BQ', 'BS', 'BZ', 'CR', 'CU', 'CW', 'DM', 'DO', 'GD', 'GP', 'GT', 'HN', 'HT', 'JM', 'KN', 'KY', 'LC', 'MF', 'MQ', 'MS', 'NI', 'PA', 'PR', 'SV', 'SX', 'TC', 'TT', 'VC', 'VG', 'VI'],
        'América do Sul' => ['AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PE', 'PY', 'SR', 'UY', 'VE'],
        'Ásia' => ['AE', 'AF', 'AM', 'AZ', 'BD', 'BH', 'BN', 'BT', 'CN', 'GE', 'HK', 'ID', 'IL', 'IN', 'IQ', 'IR', 'JO', 'JP', 'KG', 'KH', 'KP', 'KR', 'KW', 'KZ', 'LA', 'LB', 'LK', 'MM', 'MN', 'MO', 'MV', 'MY', 'NP', 'OM', 'PH', 'PK', 'PS', 'QA', 'SA', 'SG', 'SY', 'TH', 'TJ', 'TL', 'TM', 'TR', 'TW', 'UZ', 'VN', 'YE'],
        'Europa' => ['AD', 'AL', 'AT', 'BA', 'BE', 'BG', 'BY', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO', 'FR', 'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI', 'LT', 'LU', 'LV', 'MC', 'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE', 'SI', 'SJ', 'SK', 'SM', 'UA', 'VA', 'XK'],
        'Oceania' => ['AS', 'AU', 'CK', 'FJ', 'FM', 'GU', 'KI', 'MH', 'MP', 'NC', 'NF', 'NR', 'NU', 'NZ', 'PF', 'PG', 'PN', 'PW', 'SB', 'TK', 'TO', 'TV', 'UM', 'VU', 'WF', 'WS']
    ];

    $codigoPais = strtoupper($codigoPais);
    foreach ($continentes as $continente => $paises) {
        if (in_array($codigoPais, $paises, true)) {
            return $continente;
        }
    }

    return '';
}

function filtrarLocalizacoesPorContinente($localizacoes, $continente) {
    if (empty($continente)) {
        return $localizacoes;
    }

    $filtradas = [];
    foreach ($localizacoes as $localizacao) {
        if (!empty($localizacao['country']) && continenteDoPais($localizacao['country']) === $continente) {
            $filtradas[] = $localizacao;
        }
    }

    return $filtradas;
}

function montarNomeCompleto($localizacao, $fallbackCidade, $fallbackEstado, $fallbackPais) {
    $nome = isset($localizacao['local_names']['pt']) ? $localizacao['local_names']['pt'] : (isset($localizacao['name']) ? $localizacao['name'] : $fallbackCidade);
    $partes = [$nome];

    if (!empty($localizacao['state'])) {
        $partes[] = $localizacao['state'];
    } elseif (!empty($fallbackEstado)) {
        $partes[] = $fallbackEstado;
    }

    if (!empty($localizacao['country'])) {
        $partes[] = $localizacao['country'];
    } elseif (!empty($fallbackPais)) {
        $partes[] = $fallbackPais;
    }

    return implode(', ', array_unique(array_filter($partes)));
}

function entregarRespostaClima($response, $localizacao = null, $cidade = '', $estado = '', $pais = '', $continente = '') {
    $dadosClima = json_decode($response, true);
    if (!is_array($dadosClima)) {
        responderErro('Resposta inválida da API de clima.', 500);
    }

    if ($localizacao) {
        $dadosClima['localizacao'] = [
            'nome' => isset($localizacao['local_names']['pt']) ? $localizacao['local_names']['pt'] : (isset($localizacao['name']) ? $localizacao['name'] : $cidade),
            'estado' => isset($localizacao['state']) ? $localizacao['state'] : $estado,
            'pais' => isset($localizacao['country']) ? $localizacao['country'] : $pais,
            'continente' => $continente,
            'nome_completo' => montarNomeCompleto($localizacao, $cidade, $estado, $pais)
        ];
    }

    echo json_encode($dadosClima, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Quando há filtros, primeiro resolve o local exato e depois consulta o clima por coordenadas.
if (!empty($pais) || !empty($estado) || !empty($continente)) {
    $consultaLocal = [];
    $consultaLocal[] = $cidade;
    if (!empty($estado)) {
        $consultaLocal[] = $estado;
    }
    if (!empty($pais)) {
        $consultaLocal[] = $pais;
    }

    $geoUrl = 'https://api.openweathermap.org/geo/1.0/direct?' . http_build_query([
        'q' => implode(',', $consultaLocal),
        'limit' => 5,
        'appid' => $apiKey
    ]);

    $geoResult = chamarApi($geoUrl);

    if ($geoResult['response'] === false) {
        responderErro('Erro ao localizar cidade com os filtros informados.', 500, $geoResult['curlError']);
    }

    if ($geoResult['httpCode'] >= 400) {
        responderErro(mensagemErroOpenWeather($geoResult['response'], $geoResult['httpCode']), $geoResult['httpCode']);
    }

    $localizacoes = json_decode($geoResult['response'], true);
    if (!is_array($localizacoes) || count($localizacoes) === 0) {
        responderErro('Local não encontrado com esses filtros. Confira cidade, estado/província e país.', 404);
    }

    $localizacoesFiltradas = filtrarLocalizacoesPorContinente($localizacoes, $continente);
    if (!empty($continente) && count($localizacoesFiltradas) === 0) {
        responderErro('Local não encontrado no continente selecionado. Escolha também o país para aumentar a precisão.', 404);
    }
    if (count($localizacoesFiltradas) > 0) {
        $localizacoes = $localizacoesFiltradas;
    }

    $localizacao = $localizacoes[0];
    $weatherUrl = 'https://api.openweathermap.org/data/2.5/weather?' . http_build_query([
        'lat' => $localizacao['lat'],
        'lon' => $localizacao['lon'],
        'appid' => $apiKey,
        'units' => 'metric',
        'lang' => 'pt_br'
    ]);

    $weatherResult = chamarApi($weatherUrl);

    if ($weatherResult['response'] === false) {
        responderErro('Erro ao conectar com a API de clima.', 500, $weatherResult['curlError']);
    }

    if ($weatherResult['httpCode'] >= 400) {
        responderErro(mensagemErroOpenWeather($weatherResult['response'], $weatherResult['httpCode']), $weatherResult['httpCode']);
    }

    entregarRespostaClima($weatherResult['response'], $localizacao, $cidade, $estado, $pais, $continente);
}

// Busca simples original: cidade sem filtros adicionais.
$url = 'https://api.openweathermap.org/data/2.5/weather?' . http_build_query([
    'q' => $cidade,
    'appid' => $apiKey,
    'units' => 'metric',
    'lang' => 'pt_br'
]);

$result = chamarApi($url);

if ($result['response'] === false) {
    http_response_code(500);
    responderErro('Erro ao conectar com a API de clima.', 500, $result['curlError']);
}

// Se a API retornar um erro (ex: 404 para cidade não encontrada)
if ($result['httpCode'] >= 400) {
    responderErro(mensagemErroOpenWeather($result['response'], $result['httpCode']), $result['httpCode']);
}

// Retorna os dados originais da API para o frontend
entregarRespostaClima($result['response']);
?>
