<?php

header('Content-Type: application/json');

// =====================================
// RECEBE DADOS DO FRONTEND
// =====================================

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {

    echo json_encode([
        "resposta" => "Nenhum dado enviado."
    ]);

    exit;
}

// =====================================
// DADOS DO CLIMA
// =====================================

$temperatura = $input['temperatura'];
$descricao   = $input['descricao'];
$umidade     = $input['umidade'];
$vento       = $input['vento'];
$sensacao    = $input['sensacao'];

// =====================================
// OPENROUTER
// =====================================

$apiKey = "SUA_CHAVE_OPENROUTER";

// URL API
$url = "https://openrouter.ai/api/v1/chat/completions";

// =====================================
// PROMPT HUMANIZADO
// =====================================

$prompt = "

Você é um assistente virtual de clima moderno, amigável e inteligente.

Seu objetivo é conversar com o usuário de forma NATURAL e HUMANIZADA.

IMPORTANTE:

- Nunca responda exatamente igual.
- Sempre varie as palavras.
- Dê respostas criativas.
- Fale como uma IA moderna.
- Seja simpático e descontraído.
- Você pode usar emojis moderadamente.
- Faça comentários naturais sobre o clima.
- Evite respostas robóticas.
- Nunca diga que é um sistema automático.
- Não use textos genéricos repetitivos.

DADOS DO CLIMA:

Temperatura: {$temperatura}°C
Sensação térmica: {$sensacao}°C
Condição do clima: {$descricao}
Umidade: {$umidade}%
Vento: {$vento} km/h

Sua resposta deve:

1. Comentar o clima de forma natural.
2. Sugerir roupas apropriadas.
3. Recomendar atividades.
4. Dar cuidados importantes.
5. Parecer uma conversa humana.

Responda em português do Brasil.

Use HTML simples:
<h3>, <p>, <ul>, <li>

Não use markdown.

";

// =====================================
// DADOS ENVIADOS PARA IA
// =====================================

$dados = [

    "model" => "deepseek/deepseek-chat-v3-0324",

    "messages" => [

        [
            "role" => "user",

            "content" => $prompt
        ]

    ],

    "temperature" => 1.1,

    "max_tokens" => 500

];

// =====================================
// CURL
// =====================================

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_POST, true);

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

curl_setopt($ch, CURLOPT_TIMEOUT, 30);

curl_setopt($ch, CURLOPT_HTTPHEADER, [

    'Authorization: Bearer ' . $apiKey,

    'Content-Type: application/json',

    'HTTP-Referer: http://localhost',

    'X-Title: Projeto Clima IA'

]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));

// =====================================
// EXECUTA
// =====================================

$response = curl_exec($ch);

// =====================================
// ERRO CURL
// =====================================

if(curl_errno($ch)){

    echo json_encode([

        "resposta" => "

        <h3>Erro</h3>

        <p>
            Não foi possível conectar com a IA no momento.
        </p>

        "

    ]);

    exit;
}

curl_close($ch);

// =====================================
// CONVERTE JSON
// =====================================

$resultado = json_decode($response, true);

// =====================================
// RESPOSTA IA
// =====================================

if(isset($resultado['choices'][0]['message']['content'])){

    $texto =
    $resultado['choices'][0]['message']['content'];

    echo json_encode([

        "resposta" => $texto

    ]);

}else{

    // =====================================
    // FALLBACK HUMANIZADO
    // =====================================

    if($temperatura <= 15){

        $fallback = "

        <h3>🥶 Clima mais frio hoje</h3>

        <p>
            Parece um ótimo momento para roupas mais quentinhas e confortáveis.
        </p>

        <ul>
            <li>Casaco ou moletom</li>
            <li>Calça confortável</li>
            <li>Tênis fechado</li>
        </ul>

        <p>
            Talvez seja um bom dia para assistir algo, tomar um café ou relaxar em casa ☕
        </p>

        ";

    }elseif($temperatura <= 25){

        $fallback = "

        <h3>🌤️ Clima agradável</h3>

        <p>
            O tempo está bem equilibrado hoje, ótimo para sair um pouco.
        </p>

        <ul>
            <li>Camiseta leve</li>
            <li>Calça confortável</li>
            <li>Roupas casuais</li>
        </ul>

        <p>
            Caminhadas e passeios ao ar livre podem ser uma ótima ideia 😄
        </p>

        ";

    }else{

        $fallback = "

        <h3>☀️ Dia quente</h3>

        <p>
            Hoje o calor está marcando presença, então vale apostar em roupas leves.
        </p>

        <ul>
            <li>Shorts</li>
            <li>Camisetas leves</li>
            <li>Roupas frescas</li>
        </ul>

        <p>
            Não esqueça de se hidratar bastante e evitar sol forte por muito tempo 💧
        </p>

        ";
    }

    echo json_encode([

        "resposta" => $fallback

    ]);

}