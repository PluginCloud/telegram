<?php


namespace PluginCloud\Telegram;


use AurimasNiekis\TdLibSchema\Chat;
use AurimasNiekis\TdLibSchema\DeleteMessages;
use AurimasNiekis\TdLibSchema\FormattedText;
use AurimasNiekis\TdLibSchema\GetChat;
use AurimasNiekis\TdLibSchema\GetMe;
use AurimasNiekis\TdLibSchema\GetMessage;
use AurimasNiekis\TdLibSchema\InputMessageText;
use AurimasNiekis\TdLibSchema\Message;
use AurimasNiekis\TdLibSchema\MessageSchedulingStateSendAtDate;
use AurimasNiekis\TdLibSchema\Ok;
use AurimasNiekis\TdLibSchema\ParseTextEntities;
use AurimasNiekis\TdLibSchema\ReplyMarkupInlineKeyboard;
use AurimasNiekis\TdLibSchema\SendMessage;
use AurimasNiekis\TdLibSchema\SendMessageOptions;
use AurimasNiekis\TdLibSchema\TextParseModeHTML;
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

    public function sendPureTextMessage(int $chatId, string $text, int $messageId = 0): Message
    {
        $this->client->sync_send(new GetChat($chatId), Chat::class);

        $formattedText = $this->client->sync_send(new ParseTextEntities($text, new TextParseModeHTML()), FormattedText::class);

        if (!($formattedText instanceof FormattedText)) return null;

        $content = new InputMessageText($formattedText, true, true);

        $schedulingState = new MessageSchedulingStateSendAtDate(time());

        $options = new SendMessageOptions(false, true, $schedulingState);

        $replyMarkup = new ReplyMarkupInlineKeyboard([]);
        $this->client->sync_send(new GetMessage($chatId, $messageId), Message::class);
        $sendMessage = new SendMessage($chatId, $messageId, $options, $replyMarkup, $content);

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

    public function deleteMessage(int $chatId, array $messageId, bool $revoke = true): bool
    {
        $result = $this->client->sync_send(new DeleteMessages($chatId, $messageId, $revoke), Ok::class);
        if ($result instanceof Ok) {
            return true;
        }
        return false;
    }
}