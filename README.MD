# 说明
此库用于在php中操作tdlib，目前处于开发阶段。

# 环境
tdlib = 1.6.0

# 例子
```php
$params = new \PluginCloud\Telegram\Parameter();
$params->apiId = 12345;
$params->apiHash = "xxx";
$params->databaseDirectory = "./app/botDatabase";
$params->filesDirectory = "./app/botFile";

$client = new \PluginCloud\Telegram\Client();

$libPath = "/tdlib_file/path";

$client->setLibPath($libPath)->setParameter($params)->authorizationState();

$client->addSocket5Proxy("127.0.0.1", 7891, true);
if ($client->isFirstInit()) {
    $token = "bot_token";

    $auth = $client->loginByBot($token);
}


var_dump($client->getMe());
```
