<?php


namespace PluginCloud\Telegram;


use AurimasNiekis\FFI\TdLib;
use AurimasNiekis\TdLibSchema\AddProxy;
use AurimasNiekis\TdLibSchema\CheckAuthenticationBotToken;
use AurimasNiekis\TdLibSchema\CheckAuthenticationCode;
use AurimasNiekis\TdLibSchema\CheckDatabaseEncryptionKey;
use AurimasNiekis\TdLibSchema\Error;
use AurimasNiekis\TdLibSchema\GetMe;
use AurimasNiekis\TdLibSchema\Ok;
use AurimasNiekis\TdLibSchema\PhoneNumberAuthenticationSettings;
use AurimasNiekis\TdLibSchema\Proxy;
use AurimasNiekis\TdLibSchema\ProxyTypeHttp;
use AurimasNiekis\TdLibSchema\ProxyTypeMtproto;
use AurimasNiekis\TdLibSchema\ProxyTypeSocks5;
use AurimasNiekis\TdLibSchema\SetAuthenticationPhoneNumber;
use AurimasNiekis\TdLibSchema\SetDatabaseEncryptionKey;
use AurimasNiekis\TdLibSchema\SetLogVerbosityLevel;
use AurimasNiekis\TdLibSchema\SetTdlibParameters;
use AurimasNiekis\TdLibSchema\TdFunction;
use AurimasNiekis\TdLibSchema\TdObject;
use AurimasNiekis\TdLibSchema\TdSchemaRegistry;
use AurimasNiekis\TdLibSchema\UpdateAuthorizationState;
use AurimasNiekis\TdLibSchema\User;

class Client
{
    private const RECEIVE_TIMEOUT = 10;

    private TdLib $lib;
    private string $lib_path;
    private Parameter $parameter;
    private string $encryption_key;

    public function __construct()
    {
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
        $this->lib->execute(new SetLogVerbosityLevel(1));
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
        $this->sync_send(new SetTdlibParameters($this->parameter), Ok::class);
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
        $this->sync_send(new CheckDatabaseEncryptionKey($this->encryption_key), Ok::class);
        $this->sync_send(new SetDatabaseEncryptionKey($this->encryption_key), Ok::class);
        return $this;
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
     * 接受数据
     * @param int $timeout
     * @return TdObject
     */
    public function receive(int $timeout = self::RECEIVE_TIMEOUT)
    {
        $response = $this->lib->receive($timeout);
        if (is_null($response)) {//TODO 如果返回的数据是null，则重复执行
            return $this->receive($timeout);
        }

        $response = TdSchemaRegistry::fromArray($response);
        if ($response instanceof Error) {
            dump($response);die;
        }
        return $response;
    }

    /**
     * @param TdFunction $request 请求的类
     * @param string|null $wait_class 需要返回的类名称
     * @return TdObject
     */
    public function sync_send(TdFunction $request, string $wait_class = null)
    {
        $this->lib->send($request);

        if (is_null($wait_class)) {
            return $this->receive();
        }

        while (true) {
            $response = $this->receive();

            if (get_class($response) === $wait_class) {
                return $response;
            }
        }
    }
}
