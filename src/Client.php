<?php


namespace PluginCloud\Telegram;


use AurimasNiekis\FFI\TdLib;
use AurimasNiekis\TdLibSchema\AddProxy;
use AurimasNiekis\TdLibSchema\AuthorizationStateWaitEncryptionKey;
use AurimasNiekis\TdLibSchema\AuthorizationStateWaitTdlibParameters;
use AurimasNiekis\TdLibSchema\CheckAuthenticationBotToken;
use AurimasNiekis\TdLibSchema\CheckAuthenticationCode;
use AurimasNiekis\TdLibSchema\CheckDatabaseEncryptionKey;
use AurimasNiekis\TdLibSchema\GetMe;
use AurimasNiekis\TdLibSchema\Ok;
use AurimasNiekis\TdLibSchema\PhoneNumberAuthenticationSettings;
use AurimasNiekis\TdLibSchema\Proxy;
use AurimasNiekis\TdLibSchema\ProxyTypeHttp;
use AurimasNiekis\TdLibSchema\ProxyTypeMtproto;
use AurimasNiekis\TdLibSchema\ProxyTypeSocks5;
use AurimasNiekis\TdLibSchema\SetAuthenticationPhoneNumber;
use AurimasNiekis\TdLibSchema\SetDatabaseEncryptionKey;
use AurimasNiekis\TdLibSchema\SetTdlibParameters;
use AurimasNiekis\TdLibSchema\TdFunction;
use AurimasNiekis\TdLibSchema\TdObject;
use AurimasNiekis\TdLibSchema\TdSchemaRegistry;
use AurimasNiekis\TdLibSchema\UpdateAuthorizationState;
use AurimasNiekis\TdLibSchema\UpdateUser;

class Client
{
    private const RECEIVE_TIMEOUT = 10;

    private TdLib $lib;
    private string $lib_path;
    private Parameter $parameter;
    private string $encryption_key;

    private bool $first_init = true;

    public function __construct()
    {
        $this->lib = new TdLib();
        try {
            $this->encryption_key = base64_encode(random_bytes(32));
        } catch (\Exception $e) {
        }
    }

    /**
     * 设置TdLib路径
     * @param string $lib_path TdLib所在路径
     * @return Client
     */
    public function setLibPath(string $lib_path): Client
    {
        $this->lib_path = $lib_path;
        $this->lib = new TdLib($this->lib_path);
        return $this;
    }

    /**
     * 设置TdLibParameters参数
     * @param Parameter $parameter
     * @return Client
     */
    public function setParameter(Parameter $parameter): Client
    {
        $this->parameter = $parameter;
        $dbFilePath = implode(DIRECTORY_SEPARATOR, [$parameter->databaseDirectory, "db.sqlite"]);
        if (file_exists($dbFilePath)) {
            $this->first_init = false;
        }
        return $this;
    }

    /**
     * 设置加密key
     * @param string $encryption_key 加密key
     * @return Client
     */
    public function setEncryptionKey(string $encryption_key): Client
    {
        $this->encryption_key = $encryption_key;
        return $this;
    }

    /**
     * 授权
     * @return TdObject
     * @throws \JsonException
     */
    public function authorizationState()
    {
        $waitTdlibParameters = $this->wait_receive_by_class(UpdateAuthorizationState::class);
        if ($waitTdlibParameters->getAuthorizationState() instanceof AuthorizationStateWaitTdlibParameters) {
            $waitEncryption = $this->sync_send(new SetTdlibParameters($this->parameter));
            if ($this->wait_receive_by_class(Ok::class, $waitEncryption)) {
                if ($waitEncryption->getAuthorizationState() instanceof AuthorizationStateWaitEncryptionKey) {
                    $checkEncryptionResponse = $this->sync_send(new CheckDatabaseEncryptionKey($this->encryption_key));
                    if ($this->wait_receive_by_class(Ok::class, $checkEncryptionResponse)) {
                        $setEncryptionResponse = $this->sync_send(new SetDatabaseEncryptionKey($this->encryption_key));
                        if ($this->wait_receive_by_class(Ok::class, $setEncryptionResponse)) {
                            return $setEncryptionResponse;
                        }
                    }
                    return $checkEncryptionResponse;
                }
            }
            return $waitEncryption;
        }
    }

    /**
     * 添加Http代理类型
     * @param string $host 代理主机地址
     * @param int $port 代理主机端口
     * @param bool $enable 设置的同时开启代理
     * @param string $username Http用户名
     * @param string $password Http密码
     * @param bool $httpOnly 仅支持HTTP请求且不支持通过HTTP CONNECT方法的透明TCP连接，则传递true
     * @return TdObject
     * @throws \JsonException
     */
    public function addHttpProxy(
        string $host, int $port = 80, bool $enable = true,
        string $username = "", string $password = "", bool $httpOnly = true)
    {
        $params = new ProxyTypeHttp($username, $password, $httpOnly);
        return $this->sync_send(new AddProxy($host, $port, $enable, $params), Proxy::class);
    }

    /**
     * 添加Socket5代理类型
     * @param string $host 代理主机地址
     * @param int $port 代理主机端口
     * @param bool $enable 设置的同时开启代理
     * @param string $username Socket5用户名
     * @param string $password Socket5密码
     * @return TdObject
     * @throws \JsonException
     */
    public function addSocket5Proxy(
        string $host, int $port = 80, bool $enable = true,
        string $username = "", string $password = "")
    {
        $params = new ProxyTypeSocks5($username, $password);
        return $this->sync_send(new AddProxy($host, $port, $enable, $params), Proxy::class);
    }

    /**
     * 添加Mtproto代理类型
     * @param string $host 代理主机地址
     * @param int $port 代理主机端口
     * @param bool $enable 设置的同时开启代理
     * @param string $secret Mtproto的秘钥
     * @return TdObject
     * @throws \JsonException
     */
    public function addMtprotoProxy(
        string $host, int $port = 80, bool $enable = true, string $secret = "")
    {
        $params = new ProxyTypeMtproto($secret);
        return $this->sync_send(new AddProxy($host, $port, $enable, $params), Proxy::class);
    }

    /**
     * 运行机器人
     * @param string $token 机器人TOKEN
     * @return TdObject
     * @throws \JsonException
     */
    public function loginByBot(string $token)
    {
        return $this->sync_send(new CheckAuthenticationBotToken($token), Ok::class);
    }

    public function loginByUser(string $phone_number, string $password = null, PhoneNumberAuthenticationSettings $setting = null)
    {
        if (is_null($setting)) {
            $setting = new PhoneNumberAuthenticationSettings(false, false, false);
        }
        return $this->sync_send(new SetAuthenticationPhoneNumber($phone_number, $setting), UpdateAuthorizationState::class);
    }

    public function setAuthenticationCode(string $code)
    {
        return $this->sync_send(new CheckAuthenticationCode($code), Ok::class);
    }

    /**
     * 获取当前登录账户的信息
     * @return TdObject
     * @throws \JsonException
     */
    public function getMe()
    {
        return $this->sync_send(new GetMe(), UpdateUser::class);
    }

    /**
     * 接受数据
     * @param int $timeout
     * @return TdObject
     * @throws \JsonException
     */
    public function receive(int $timeout = self::RECEIVE_TIMEOUT)
    {
        $response = $this->lib->receive($timeout);
        if (is_null($response)) {//TODO 如果返回的数据是null，则重复执行
            return $this->receive($timeout);
        }

        return TdSchemaRegistry::fromArray($response);
    }

    /**
     * @param string $className 等待的类名
     * @param TdObject|null $response 响应的类
     * @return TdObject
     * @throws \JsonException
     */
    public function wait_receive_by_class(string $className, TdObject $response = null)
    {
        if (!is_null($response) && $className === get_class($response)) {
            return $response;
        }
        while (true) {
            $response = $this->receive();
            if ($className === get_class($response)) {
                return $response;
            }
        }
    }

    /**
     * @param TdFunction $request 请求的类
     * @param string|null $wait_class 需要返回的类名称
     * @return TdObject
     * @throws \JsonException
     */
    public function sync_send(TdFunction $request, string $wait_class = null)
    {
        $this->lib->send($request);

        if (is_null($wait_class)) {
            return $this->receive();
        }

        while (true) {
            $response = $this->receive();
            dump($response);
            if (get_class($response) === $wait_class) {
                return $response;
            }
        }
    }

    /**
     * @return bool
     */
    public function isFirstInit(): bool
    {
        return $this->first_init;
    }
}
