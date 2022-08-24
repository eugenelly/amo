<?php

declare(strict_types=1);

namespace App\Helpers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Exceptions\AmoCRMApiHttpClientException;
use AmoCRM\Exceptions\AmoCRMApiNoContentException;
use AmoCRM\Exceptions\BadTypeException;
use Exception;
use League\OAuth2\Client\Token\AccessToken;

use const DIRECTORY_SEPARATOR;
use const TOKEN_FILE;

define('TOKEN_FILE', DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json');

/**
 * Класс для работы с AmoCRM
 *
 * @link https://www.amocrm.ru/
 */
class AmoHelper
{
    /** @var AmoCRMApiClient Объект API для работы с AmoCRM */
    public AmoCRMApiClient $api;

    /** @var string ID канала (ID интеграции) */
    private string $clientId;

    /** @var string Секретный ключ (Секрет интеграции) */
    private string $clientSecret;

    /**
     * @var string Ссылка для перенаправления.
     *
     * Redirect URI указанный в настройках интеграции.
     * Должен четко совпадать с тем, что указан в настройках.
     */
    private string $redirectUri;

    /**
     * @var string Access Token в формате JWT.
     *
     * Токен имеет ограниченный срок жизни (1 сутки) и может быть получен с помощью кода авторизации или Refresh токена.
     */
    private string $accessToken;

    /** @var string Refresh Token. Refresh токен действует всего 3 месяца. */
    private string $refreshToken;

    /** @var int Через сколько истекает токен */
    private int $expires;

    /** @var string Базовый домен */
    private string $baseDomain;

    /**
     * Конструктор для класса AmoCRM
     *
     * @param string $clientId ID канала (ID интеграции)
     * @param string $clientSecret Секретный ключ (Секрет интеграции)
     * @param string $redirectUri Ссылка для перенаправления.
     * Redirect URI указанный в настройках интеграции. Должен четко совпадать с тем, что указан в настройках.
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUri
    )
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;

        $this->api = new AmoCRMApiClient(
            $this->clientId,
            $this->clientSecret,
            $this->redirectUri
        );
    }

    /**
     * Подключение к AmoCRM с получением или использованием токена взятого из файла
     *
     * @return $this Объект AmoHelper
     * @throws BadTypeException
     */
    public function connect(): AmoHelper
    {
        $accessToken = self::getToken();

        if (!isset($accessToken)) {
            if (isset($_GET['referer'])) {
                $this->api->setAccountBaseDomain($_GET['referer']);
            }

            if (!isset($_GET['code'])) {
                $state = bin2hex(random_bytes(16));
                $_SESSION['oauth2state'] = $state;

                if (isset($_GET['button'])) {
                    echo $this->api->getOAuthClient()->getOAuthButton([
                        'title' => 'Установить интеграцию',
                        'compact' => true,
                        'class_name' => 'className',
                        'color' => 'default',
                        'error_callback' => 'handleOauthError',
                        'state' => $state,
                    ]);
                    die;
                } else {
                    $authorizationUrl = $this->api->getOAuthClient()->getAuthorizeUrl(
                        [
                            'state' => $state,
                            'mode' => 'post_message',
                        ]
                    );
                    header('Location: ' . $authorizationUrl);
                    die;
                }
            } elseif (
                empty($_GET['state'])
            ) {
                unset($_SESSION['oauth2state']);
                exit('Invalid state');
            }

            /**
             * Ловим обратный код
             */
            try {
                $accessToken = $this->api->getOAuthClient()->getAccessTokenByCode($_GET['code']);

                if (!$accessToken->hasExpired()) {
                    self::saveToken([
                        'accessToken' => $accessToken->getToken(),
                        'refreshToken' => $accessToken->getRefreshToken(),
                        'expires' => $accessToken->getExpires(),
                        'baseDomain' => $this->api->getAccountBaseDomain(),
                    ]);
                }
            } catch (Exception $e) {
                die((string)$e);
            }
        } else {
            $accessToken = self::getToken();
        }

        $this->api->setAccessToken($accessToken);
        $this->api->setAccountBaseDomain($this->baseDomain);

        return $this;
    }

    /**
     * Сохранение токена в файл
     *
     * @param array $accessToken Токен для идентификации в AmoCRM
     */
    private function saveToken(array $accessToken): void
    {
        if (
            !isset($accessToken)
            && !isset($accessToken['accessToken'])
            && !isset($accessToken['refreshToken'])
            && !isset($accessToken['expires'])
            && !isset($accessToken['baseDomain'])
        ) {
            exit('Invalid access token ' . var_export($accessToken, true));
        }

        $this->accessToken = $accessToken['accessToken'];
        $this->refreshToken = $accessToken['refreshToken'];
        $this->expires = $accessToken['expires'];
        $this->baseDomain = $accessToken['baseDomain'];

        $data = [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expires' => $this->expires,
            'baseDomain' => $this->baseDomain,
        ];

        file_put_contents(
            TOKEN_FILE,
            json_encode($data)
        );
    }

    /**
     * Получение токена из файла
     *
     * @return AccessToken|null Токен считанный из файла для идентификации в AmoCRM
     */
    public function getToken(): ?AccessToken
    {
        if (!file_exists(TOKEN_FILE)) {
            return null;
        }

        if (file_get_contents(TOKEN_FILE) === '') { // todo-task переписать на curl
            return null;
        }

        $accessToken = json_decode(
            file_get_contents( // todo-task переписать на curl
                TOKEN_FILE
            ),
            true
        );

        if (
            !isset($accessToken)
            && !isset($accessToken['accessToken'])
            && !isset($accessToken['refreshToken'])
            && !isset($accessToken['expires'])
            && !isset($accessToken['baseDomain'])
        ) {
            return null;
        }

        $this->accessToken = $accessToken['accessToken'];
        $this->refreshToken = $accessToken['refreshToken'];
        $this->expires = (int)$accessToken['expires'];
        $this->baseDomain = $accessToken['baseDomain'];

        return new AccessToken([
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires' => $this->expires,
            'baseDomain' => $this->baseDomain,
        ]);
    }

    /**
     * Получение колекции сделок
     *
     * @return LeadsCollection Колекция сделок
     */
    public function getLeads(): LeadsCollection
    {
        try {
            $leads = $this->api->leads()->get();
        } catch (AmoCRMApiNoContentException $e) {
            die('У вас нет сделок.');
        } catch (AmoCRMApiHttpClientException $e) {
            die('Произошла ошибка http клиента.');
        } catch (Exception $e) {
            die('Произошла неизвестная ошибка.');
        }

        return $leads;
    }
}
