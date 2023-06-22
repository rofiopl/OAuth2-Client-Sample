<?php

namespace Demo;

class App
{
    private $htmlTemplate = '';

    private $clientId = 'demo';
    private $clientSecret = 'demo';
    private $redirectUri = 'http://oauth2-client.tld/callback';
    private $authServer = 'http://localhost:8002/authorize';
    private $tokenServer = 'http://localhost:8002/token';
    private $apiUri = 'http://localhost:8002/api/profile';

    public function __construct()
    {
        $this->htmlTemplate = file_get_contents(__DIR__ . '/template.html');
    }

    private function getRequestPath(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        return parse_url($requestUri, PHP_URL_PATH);
    }

    private function render(string $content)
    {
        $html = str_replace('[BODY]', $content, $this->htmlTemplate);
        echo $html;
        exit;
    }

    public function run()
    {
        $requestPath = $this->getRequestPath();
        switch ($requestPath) {
            case '/':
                $this->indexAction();
                break;
            case '/login':
                $this->loginAction();
                break;
            case '/callback':
                $this->callbackAction();
                break;
            case '/api':
                $this->apiAction();
                break;
            case '/logout':
                $this->logoutAction();
                break;
            default:
                $this->notFoundAction();
        }
    }

    private function notFoundAction()
    {
        http_response_code(404);
        echo 'Not found';
    }

    private function loginAction()
    {
        // Redirect to the authorization server.
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'profile',
        ];
        $url = $this->authServer . '?' . http_build_query($params);
        header('Location: ' . $url);
    }

    private function indexAction()
    {
        $content = '';
        if (isset($_COOKIE['access_token'])) {
            $content = '<p><a href="/api" class="btn btn-sm btn-primary">Test API Call</a></p><p><a href="/logout" class="btn btn-sm btn-light border">Logout</a></p>';
            // => https://jwt.io
            $parts = explode(".", $_COOKIE['access_token']);
            $content .= '<p>JWT Header</p><pre>' . print_r(json_decode(base64_decode($parts[0]), true), true) . '</pre>';
            $content .= '<p>JWT Payload</p><pre>' . print_r(json_decode(base64_decode($parts[1]), true), true) . '</pre>';

        }
        $this->render($content);
    }

    private function callbackAction()
    {
        $code = $_GET['code'] ?? null;
        if (null === $code) {
            $content = '<p class="alert alert-danger">No code provided</p>';
            if (isset($_GET['error_description'])) {
                $content .= '<p class="alert alert-danger">Error: ' . $_GET['error_description'] . '</p>';
            }
            $content .= '<a href="/" class="btn btn-sm btn-light border">Back</a>';
            $this->render($content);
        }
        // Swap the code for an access token.
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ];
        $ch = curl_init($this->tokenServer);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Ignore SSL for demo purposes.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $response = json_decode($response, true);
        $accessToken = $response['access_token'] ?? null;
        $content = '';
        if (!$accessToken) {
            $content = '<p class="alert alert-danger">No access token provided</p>';
            if (isset($response['hint'])) {
                $content .= '<p class="alert alert-danger">Error: ' . $response['hint'] . '</p>';
            }
            $content .= '<a href="/" class="btn btn-sm btn-light border">Back</a>';
            $this->render($content);
        }

        // Save the access token in a cookie.
        setcookie('access_token', $accessToken, time() + 3600);
        // Redirect to the home page.
        header('Location: /');
    }

    private function apiAction()
    {
        // Get the access token from the cookie.
        $accessToken = $_COOKIE['access_token'] ?? null;
        if (null === $accessToken) {
            $this->render('No access token provided');
        }
        // Call the API.
        $ch = curl_init($this->apiUri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Ignore SSL for demo purposes.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $response = json_decode($response, true);
        $content = '<p class="alert alert-info">Calling API on ' . $this->apiUri . '</p>';
        $content .= '<p>With access token <textarea class="form-control">' . $accessToken . '</textarea></p>';
        $content .= '<p>Response</p><pre>' . print_r($response, true) . '</pre>';
        $content .= '<a href="/" class="btn btn-sm btn-light border">Back</a>';
        $this->render($content);
    }

    private function logoutAction()
    {
        setcookie('access_token', '', time() - 3600);
        header('Location: /');
    }
}

$app = new App();
$app->run();
