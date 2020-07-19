<?php


namespace PluginCloud\Telegram;


use AurimasNiekis\TdLibSchema\Chat;
use AurimasNiekis\TdLibSchema\DeleteMessages;
use AurimasNiekis\TdLibSchema\FormattedText;
use AurimasNiekis\TdLibSchema\GetChat;
use AurimasNiekis\TdLibSchema\GetMe;
use AurimasNiekis\TdLibSchema\GetMessage;
use AurimasNiekis\TdLibSchema\InlineKeyboardButton;
use AurimasNiekis\TdLibSchema\InlineKeyboardButtonType;
use AurimasNiekis\TdLibSchema\InlineKeyboardButtonTypeBuy;
use AurimasNiekis\TdLibSchema\InlineKeyboardButtonTypeCallback;
use AurimasNiekis\TdLibSchema\InlineKeyboardButtonTypeCallbackGame;
use AurimasNiekis\TdLibSchema\InlineKeyboardButtonTypeLoginUrl;
use AurimasNiekis\TdLibSchema\InlineKeyboardButtonTypeSwitchInline;
use AurimasNiekis\TdLibSchema\InlineKeyboardButtonTypeUrl;
use AurimasNiekis\TdLibSchema\InputMessageContent;
use AurimasNiekis\TdLibSchema\InputMessageText;
use AurimasNiekis\TdLibSchema\KeyboardButton;
use AurimasNiekis\TdLibSchema\KeyboardButtonType;
use AurimasNiekis\TdLibSchema\KeyboardButtonTypeRequestLocation;
use AurimasNiekis\TdLibSchema\KeyboardButtonTypeRequestPhoneNumber;
use AurimasNiekis\TdLibSchema\KeyboardButtonTypeRequestPoll;
use AurimasNiekis\TdLibSchema\KeyboardButtonTypeText;
use AurimasNiekis\TdLibSchema\Message;
use AurimasNiekis\TdLibSchema\MessageSchedulingStateSendAtDate;
use AurimasNiekis\TdLibSchema\Ok;
use AurimasNiekis\TdLibSchema\ParseTextEntities;
use AurimasNiekis\TdLibSchema\ReplyMarkup;
use AurimasNiekis\TdLibSchema\ReplyMarkupForceReply;
use AurimasNiekis\TdLibSchema\ReplyMarkupInlineKeyboard;
use AurimasNiekis\TdLibSchema\ReplyMarkupRemoveKeyboard;
use AurimasNiekis\TdLibSchema\ReplyMarkupShowKeyboard;
use AurimasNiekis\TdLibSchema\SendMessage;
use AurimasNiekis\TdLibSchema\SendMessageOptions;
use AurimasNiekis\TdLibSchema\TextParseModeHTML;
use AurimasNiekis\TdLibSchema\TextParseModeMarkdown;
use AurimasNiekis\TdLibSchema\UpdateMessageSendFailed;
use AurimasNiekis\TdLibSchema\UpdateMessageSendSucceeded;
use AurimasNiekis\TdLibSchema\User;

class App
{
    protected Client $client;
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return User
     */
    public function getMe(): User
    {
        $user = $this->client->sync_send(new GetMe(), User::class);
        if (!($user instanceof User)) return null;
        return $user;
    }

    /**
     * 发送纯文本消息
     * @param int $chatId 聊天ID
     * @param string $text 文本
     * @param int $messageId 消息ID
     * @param bool $disableWebPagePreview 显示web页面预览
     * @param bool $disableNotification 通知
     * @return Message
     */
    public function sendPureTextMessage(
        int $chatId, string $text, int $messageId,
        bool $disableWebPagePreview = true, bool $disableNotification = true): Message
    {
        $replyMarkup = new ReplyMarkupInlineKeyboard([]);

        $this->getChat($chatId);

        $this->getMessage($chatId, $messageId);

        $formattedText = $this->parseText($text);

        $options = $this->sendRealTimeMessageOption($disableNotification);

        $content = $this->inputMessageText($formattedText, $disableWebPagePreview);

        return $this->sendMessage($chatId, $content, $messageId, $options, $replyMarkup);
    }

    /**
     * 发送纯文本消息
     * @param int $chatId 聊天ID
     * @param string $text 文本
     * @param int $messageId 消息ID
     * @param ReplyMarkup $replyMarkup markup
     * @param bool $disableWebPagePreview 显示web页面预览
     * @param bool $disableNotification 通知
     * @return Message
     */
    public function sendPureTextMarkupMessage(
        int $chatId, string $text, int $messageId, ReplyMarkup $replyMarkup,
        bool $disableWebPagePreview = true, bool $disableNotification = true): Message
    {
        $this->getChat($chatId);

//        $this->getMessage($chatId, $messageId);

        $formattedText = $this->parseText($text);

        $options = $this->sendRealTimeMessageOption($disableNotification);

        $content = $this->inputMessageText($formattedText, $disableWebPagePreview);

        return $this->sendMessage($chatId, $content, $messageId, $options, $replyMarkup);
    }

    /**
     * 回复强制用户回复
     * @param bool $is_personal 针对用户
     * @return ReplyMarkupForceReply
     */
    public function replyMarkupForceReply(bool $is_personal)
    {
        return new ReplyMarkupForceReply($is_personal);
    }

    /**
     * 回复内联按钮
     * @param array $rows 按钮数组(inlineKeyboardButton)
     * @return ReplyMarkupInlineKeyboard
     */
    public function replyMarkupInlineKeyboard(array $rows)
    {
        return new ReplyMarkupInlineKeyboard($rows);
    }

    /**
     * 回复移除键盘
     * @param bool $is_personal 针对用户
     * @return ReplyMarkupRemoveKeyboard
     */
    public function replyMarkupRemoveKeyboard(bool $is_personal)
    {
        return new ReplyMarkupRemoveKeyboard($is_personal);
    }

    /**
     * 回复显示键盘
     * @param array $rows 按钮数组(keyboardButton)
     * @param bool $resize_keyword 自动调整布局
     * @param bool $one_time 使用一次
     * @param bool $is_personal 针对当前用户
     * @return ReplyMarkupShowKeyboard
     */
    public function replyMarkupShowKeyboard(array $rows = [], bool $resize_keyword = true, bool $one_time = true, bool $is_personal = true)
    {
        return new ReplyMarkupShowKeyboard($rows, $resize_keyword, $one_time, $is_personal);
    }

    /**
     * 键盘按钮
     * @param string $text 文本
     * @param KeyboardButtonType $type 按钮类型
     * @return KeyboardButton
     */
    public function keyboardButton(string $text, KeyboardButtonType $type) {
        return new KeyboardButton($text, $type);
    }

    /**
     * 内联按钮
     * @param string $text 文本
     * @param InlineKeyboardButtonType $type 按钮类型
     * @return InlineKeyboardButton
     */
    public function inlineKeyboardButton(string $text, InlineKeyboardButtonType $type)
    {
        return new InlineKeyboardButton($text, $type);
    }

    /**
     * 按下时获取用户位置（仅私人聊天可用）
     * @return KeyboardButtonTypeRequestLocation
     */
    public function keyboardButtonTypeRequestLocation()
    {
        return new KeyboardButtonTypeRequestLocation();
    }

    /**
     * 按下时获取用户手机号码（仅私人聊天可用）
     * @return KeyboardButtonTypeRequestPhoneNumber
     */
    public function keyboardButtonTypeRequestPhoneNumber()
    {
        return new KeyboardButtonTypeRequestPhoneNumber();
    }

    /**
     * 按下时发送民意调查
     * @param bool $fore_regular 仅常规民意调查
     * @param bool $force_quiz 仅测验模式
     * @return KeyboardButtonTypeRequestPoll
     */
    public function keyboardButtonTypeRequestPoll(bool $fore_regular, bool $force_quiz)
    {
        return new KeyboardButtonTypeRequestPoll($fore_regular, $force_quiz);
    }

    /**
     * 按下按钮时发送的文本
     * @return KeyboardButtonTypeText
     */
    public function keyboardButtonTypeText()
    {
        return new KeyboardButtonTypeText();
    }

    /**
     * 内联购买按钮（仅在messageInvoice可用）
     * @return InlineKeyboardButtonTypeBuy
     */
    public function inlineKeyboardButtonTypeBuy()
    {
        return new InlineKeyboardButtonTypeBuy();
    }

    /**
     * 内联回调查询按钮
     * @param string $data
     * @return InlineKeyboardButtonTypeCallback
     */
    public function inlineKeyboardButtonTypeCallback(string $data)
    {
        return new InlineKeyboardButtonTypeCallback(base64_encode($data));
    }

    /**
     * 内联游戏回调按钮（仅在messageGame上可用）
     * @return InlineKeyboardButtonTypeCallbackGame
     */
    public function inlineKeyboardButtonTypeCallbackGame()
    {
        return new InlineKeyboardButtonTypeCallbackGame();
    }

    /**
     * 内联登录按钮
     * @param string $url 登录URL
     * @param int $id 按钮唯一标识
     * @param string $forward_text 转发消息时的新文本
     * @return InlineKeyboardButtonTypeLoginUrl
     */
    public function inlineKeyboardButtonTypeLoginUrl(string $url, int $id, string $forward_text = "")
    {
        return new InlineKeyboardButtonTypeLoginUrl($url, $id, $forward_text);
    }

    /**
     * 内联查询强制插入
     * @param string $query 查询内容
     * @param bool $in_current_chant 当前聊天
     * @return InlineKeyboardButtonTypeSwitchInline
     */
    public function inlineKeyboardButtonTypeSwitchInline(string $query, bool $in_current_chant)
    {
        return new InlineKeyboardButtonTypeSwitchInline($query, $in_current_chant);
    }

    /**
     * 内联链接按钮
     * @param string $url 链接
     * @return InlineKeyboardButtonTypeUrl
     */
    public function inlineKeyboardButtonTypeUrl(string $url)
    {
        return new InlineKeyboardButtonTypeUrl($url);
    }

    /**
     * 发送消息
     * @param int $chatId 聊天ID
     * @param InputMessageContent $content
     * @param int $replyToMessageId 回复消息ID
     * @param SendMessageOptions $options 选项
     * @param ReplyMarkup $replyMarkup markup
     * @return Message
     */
    protected function sendMessage(
        int $chatId, InputMessageContent $content, int $replyToMessageId, SendMessageOptions $options,
        ReplyMarkup $replyMarkup): Message
    {
        var_dump($replyMarkup);
        $sendMessage = new SendMessage($chatId, $replyToMessageId, $options, $replyMarkup, $content);

        $oldMessage = $this->client->sync_send($sendMessage, Message::class);

        if (!($oldMessage instanceof Message))  return null;

        while (true) {
            $response = $this->client->receive();
            if ($response instanceof UpdateMessageSendSucceeded) {
                if ($response->getOldMessageId() === $oldMessage->getId()) {
                    return $response->getMessage();
                }
            }elseif ($response instanceof UpdateMessageSendFailed) {
                return null;
            }
        }
    }

    /**
     * 获取聊天信息
     * @param int $chatId 聊天ID
     * @return Chat
     */
    public function getChat(int $chatId): Chat
    {
        $result = $this->client->sync_send(new GetChat($chatId), Chat::class);
        if (!($result instanceof Chat)) return null;
        return $result;
    }

    /**
     * 获取消息信息
     * @param int $chatId 聊天ID
     * @param int $messageId 消息ID
     * @return Message
     */
    public function getMessage(int $chatId, int $messageId): Message
    {
        $result = $this->client->sync_send(new GetMessage($chatId, $messageId), Message::class);
        if (!($result instanceof Message)) return null;
        return $result;
    }

    /**
     * 删除消息
     * @param int $chatId 聊天ID
     * @param array $messageIds 消息ID
     * @param bool $revoke 为其他用户删除消息
     * @return bool
     */
    public function deleteMessage(int $chatId, array $messageIds, bool $revoke = true): bool
    {
        $result = $this->client->sync_send(new DeleteMessages($chatId, $messageIds, $revoke), Ok::class);
        if ($result instanceof Ok) {
            return true;
        }
        return false;
    }

    const TEXT_PARSE_MODE_HTML = "html";
    const TEXT_PARSE_MODE_MARKDOWN = "markdown";

    /**
     * 解析文本
     * @param string $text 文本内容
     * @param string $mode 解析模式，默认解析html
     * @return FormattedText
     */
    protected function parseText(string $text, string $mode = self::TEXT_PARSE_MODE_HTML): FormattedText
    {
        $parseMode = new TextParseModeHTML();
        if ($mode === self::TEXT_PARSE_MODE_HTML) {
            $parseMode = new TextParseModeHTML();
        }elseif ($mode === self::TEXT_PARSE_MODE_MARKDOWN) {
            $parseMode = new TextParseModeMarkdown(2);
        }
        $request = new ParseTextEntities($text, $parseMode);
        $result = $this->client->sync_send($request, FormattedText::class);

        if (!($result instanceof FormattedText)) return null;

        return $result;
    }

    /**
     * MessageSchedulingState 状态为实时
     * @return MessageSchedulingStateSendAtDate
     */
    protected function messageSchedulingStateSendRealTime(): MessageSchedulingStateSendAtDate
    {
        return new MessageSchedulingStateSendAtDate(time());
    }

    /**
     * 发送实时消息配置
     * @param bool $disableNotification
     * @return SendMessageOptions
     */
    protected function sendRealTimeMessageOption(bool $disableNotification = true): SendMessageOptions
    {
        $schedulingState = $this->messageSchedulingStateSendRealTime();

        return new SendMessageOptions($disableNotification, true, $schedulingState);
    }

    /**
     * 输入文本消息
     * @param FormattedText $formattedText
     * @param bool $disableWebPagePreview 禁止web页面预览
     * @param bool $clearDraft 清除草稿
     * @return InputMessageText
     */
    protected function inputMessageText(FormattedText $formattedText, bool $disableWebPagePreview = true, bool $clearDraft = true)
    {
        return new InputMessageText($formattedText, $disableWebPagePreview, $clearDraft);
    }
}