<?php
require_once __DIR__ . '/config/botapi.php';

// TELEGRAM API GENERAL FUNCTIONS
function getUpdate(bool $as_array = true): ?array
{
    $content = file_get_contents("php://input");
    return json_decode($content, $as_array);
}

function callMethod(string $method, ...$params): ?string
{
    // callMethod('method', 'key1', value1, 'key2', value2, ...)
    $payload = array("method" => $method);
    $len_params = count($params);
    for ($i = 0; $i < $len_params - 1; $i += 2) {
        $payload[$params[$i]] = $params[$i + 1];
    }

    $req_handle = curl_init(URL_BASE);
    curl_setopt($req_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req_handle, CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds for server connect timeout
    curl_setopt($req_handle, CURLOPT_TIMEOUT, 60); // response return timeout at 60 secs
    curl_setopt($req_handle, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($req_handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    return curl_exec($req_handle);
}

function getFileFrom(array &$message): ?array
{
    $file_types = [FILE_PHOTO, FILE_VOICE, FILE_VIDEO, FILE_AUDIO, FILE_DOCUMENT];

    foreach ($file_types as $tag) {
        if (isset($message[$tag])) {
            $file_id = $tag != FILE_PHOTO
            ? $message[$tag][FILE_ID]
            : $message[$tag][count($message[FILE_PHOTO]) - 1][FILE_ID];
            return array(FILE_ID => $file_id, 'tag' => $tag, CAPTION_TAG => $message[CAPTION_TAG] ?? '');
        }
    }
    return null;
}

function extractFromSentMessage(string &$telegram_response, string $field = MESSAGE_ID_TAG)
{
    $channel_response = json_decode($telegram_response, true);
    return $channel_response['result'][$field] ?? null;
}

function createCallbackData(string $action, ?array $params=null, $extra = null): array {
    $data = ["a" => $action, "d" => $params];
    if($extra)
        $data['x'] = $extra;
    return $data;
}

function jsonifyCallbackData(int $action, ?array $params=null, $extra = null): string
{
    return json_encode(
        createCallbackData($action, $params, $extra)
    );
}

function validateInlineData(array &$params, ...$required_keys): ?string {
    foreach($required_keys as $key) {
        if(!isset($params[$key]))
            return 'این عملیات فابل ادامه دادن نیست! علت احتمالی این فرایند غیرمعتبر بودن گزینه انتخابی یا از دست رفتن اطلاعات حین انتقال است. لطفا دوباره این فرایند رو از سر بگیرید و اگر باز با این خطا مواجه شدید از طریق منوی پشتیبانی مشکل را به ادمین اطلاع دهید. با تشکر از صبوری شما ...';
    }
    return null;
}