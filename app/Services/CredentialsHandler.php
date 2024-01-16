<?php
namespace App\Services;

class CredentialsHandler
{


    public static function getLineApiUrl()
    {
        return config('setting.line_api_url');
    }

    public static function getLineChannelSecret()
    {
        return config('setting.line_channel_secret');
    }

    public static function getLineChannelAccessToken()
    {
        return config('setting.line_channel_access_token');
    }

    public static function getLineChannelId()
    {
        return config('setting.line_channel_id');
    }

    public static function getFacebookPageAccessToken()
    {
        return config('setting.facebook_page_access_token');
    }

    public static function getFacebookHubVerifyToken()
    {
        return config('setting.facebook_hub_verify_token');
    }

}
