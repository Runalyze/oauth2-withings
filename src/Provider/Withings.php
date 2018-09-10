<?php

namespace waytohealth\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Withings extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * Withings URL.
     *
     * @const string
     */
    const BASE_WITHINGS_URL = 'https://account.health.nokia.com';

    /**
     * Withings API URL
     *
     * @const string
     */
    const BASE_WITHINGS_API_URL = 'https://api.health.nokia.com';

    /**
     * HTTP header Accept-Language.
     *
     * @const string
     */
    const HEADER_ACCEPT_LANG = 'Accept-Language';

    /**
     * HTTP header Accept-Locale.
     *
     * @const string
     */
    const HEADER_ACCEPT_LOCALE = 'Accept-Locale';

    /**
     * Withings code for Weight devices
     *
     * @const int
     */
    const APPI_WEIGHT = 1;

    const APPI_HEART = 4;

    const APPI_ACTIVITY = 16;

    const APPI_SLEEP = 44;

    const APPI_USER = 46;

    /**
     * Get authorization url to begin OAuth flow.
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return static::BASE_WITHINGS_URL.'/oauth2_user/authorize2';
    }

    /**
     * Get access token url to retrieve token.
     *
     * @param array $params
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return static::BASE_WITHINGS_URL.'/oauth2/token';
    }

    /**
     * Returns the url to retrieve the resource owners's profile/details.
     *
     * @param AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return static::BASE_WITHINGS_API_URL.'/v2/user?action=getdevice';
    }

    /**
     * Returns all scopes available from Withings.
     * It is recommended you only request the scopes you need!
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return ['user.info', 'user.metrics', 'user.activity'];
    }

    /**
     * Checks Withings API response for errors.
     *
     * @throws IdentityProviderException
     *
     * @param ResponseInterface $response
     * @param array|string      $data     Parsed response data
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            $errorMessage = '';
            if (!empty($data['errors'])) {
                foreach ($data['errors'] as $error) {
                    if (!empty($errorMessage)) {
                        $errorMessage .= ' , ';
                    }
                    $errorMessage .= implode(' - ', $error);
                }
            } else {
                $errorMessage = $response->getReasonPhrase();
            }
            throw new IdentityProviderException(
                $errorMessage,
                $response->getStatusCode(),
                $response
            );
        }
    }

    /**
     * Returns authorization parameters based on provided options.
     * Withings does not use the 'approval_prompt' param and here we remove it.
     *
     * @param array $options
     *
     * @return array Authorization parameters
     */
    protected function getAuthorizationParameters(array $options)
    {
        $params = parent::getAuthorizationParameters($options);
        unset($params['approval_prompt']);
        if (!empty($options['prompt'])) {
            $params['prompt'] = $options['prompt'];
        }

        return $params;
    }

    /**
     * Builds request options used for requesting an access token.
     *
     * @param array $params
     *
     * @return array
     */
    protected function getAccessTokenOptions(array $params)
    {
        $options = parent::getAccessTokenOptions($params);
        $options['headers']['Authorization'] =
            'Basic '.base64_encode($this->clientId.':'.$this->clientSecret);

        return $options;
    }

    /**
     * Generates a resource owner object from a successful resource owner
     * details request.
     *
     * @param array       $response
     * @param AccessToken $token
     *
     * @return FitbitUser
     */
    public function createResourceOwner(array $response, AccessToken $token)
    {
        return new FitbitUser($response);
    }

    /**
     * Returns the key used in the access token response to identify the resource owner.
     *
     * @return string|null Resource owner identifier key
     */
    protected function getAccessTokenResourceOwnerId()
    {
        return 'user_id';
    }

    /**
     * Revoke access for the given token.
     *
     * @param AccessToken $accessToken
     *
     * @return mixed
     */
    public function revoke(AccessToken $accessToken)
    {
        $options = $this->getAccessTokenOptions([]);
        $uri = $this->appendQuery(
            self::BASE_WITHINGS_API_URL.'/notify?action=revoke',
            $this->buildQueryString(['token' => $accessToken->getToken()])
        );
        $request = $this->getRequest(self::METHOD_POST, $uri, $options);

        return $this->getResponse($request);
    }

    public function parseResponse(ResponseInterface $response)
    {
        return parent::parseResponse($response);
    }
}
