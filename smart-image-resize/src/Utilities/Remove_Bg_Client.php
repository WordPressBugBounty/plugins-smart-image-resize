<?php

namespace WP_Smart_Image_Resize\Utilities;

defined('ABSPATH') || exit;

/**
 * Thin HTTP client for the remove.bg REST API.
 *
 * @see https://www.remove.bg/api
 */
class Remove_Bg_Client
{
    const API_BASE = 'https://api.remove.bg/v1.0';

    /**
     * Remove the background from an image file via the remove.bg API.
     *
     * @param string $file_path Path to the source image on disk.
     * @param string $api_key   remove.bg API key.
     *
     * @return array|\WP_Error {
     *     @type string $body Raw image bytes returned by the API.
     *     @type string $mime Mime type of the returned image.
     * }
     */
    public static function remove_background($file_path, $api_key)
    {
        if (empty($api_key)) {
            return new \WP_Error('removebg_no_key', __('No remove.bg API key configured.', 'wp-smart-image-resize'));
        }

        if (!is_readable($file_path)) {
            return new \WP_Error('removebg_no_file', __('Source image is not readable.', 'wp-smart-image-resize'));
        }

        $boundary  = wp_generate_password(24, false);
        $file_size = @filesize($file_path);
        $start     = microtime(true);

        error_log(sprintf(
            'Smart Image Resize [remove.bg] → request: file=%s size=%s',
            wp_basename($file_path),
            $file_size !== false ? size_format($file_size) : 'unknown'
        ));

        $response = wp_remote_post(self::API_BASE . '/removebg', [
            'timeout' => 60,
            'headers' => [
                'X-Api-Key'    => $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => self::build_multipart_body($file_path, $boundary),
        ]);

        $elapsed = round(microtime(true) - $start, 2);

        if (is_wp_error($response)) {
            error_log(sprintf(
                'Smart Image Resize [remove.bg] ✗ wp_error after %.2fs: %s',
                $elapsed,
                $response->get_error_message()
            ));
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        if ($status !== 200) {
            $error_msg = self::extract_error_message($body, $status);
            error_log(sprintf(
                'Smart Image Resize [remove.bg] ✗ HTTP %d after %.2fs: %s',
                $status,
                $elapsed,
                $error_msg
            ));
            return new \WP_Error('removebg_api_error', $error_msg, $status);
        }

        $result_size = strlen($body);
        error_log(sprintf(
            'Smart Image Resize [remove.bg] ✓ success after %.2fs: returned %s',
            $elapsed,
            size_format($result_size)
        ));

        return [
            'body' => $body,
            'mime' => 'image/png',
        ];
    }

    /**
     * Fetch the account's remaining credits (paid + free calls).
     *
     * @param string $api_key
     *
     * @return array|\WP_Error {
     *     @type float|null $credits    Remaining paid/subscription credits, if reported.
     *     @type int|null   $free_calls Remaining free API calls, if reported.
     * }
     */
    public static function get_account_credits($api_key)
    {
        if (empty($api_key)) {
            return new \WP_Error('removebg_no_key', __('No remove.bg API key configured.', 'wp-smart-image-resize'));
        }

        $response = wp_remote_get(self::API_BASE . '/account', [
            'timeout' => 20,
            'headers' => ['X-Api-Key' => $api_key],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        if ($status !== 200) {
            return new \WP_Error('removebg_api_error', self::extract_error_message($body, $status), $status);
        }

        $data = json_decode($body, true);

        $credits    = isset($data['data']['attributes']['credits']['total']) ? (float) $data['data']['attributes']['credits']['total'] : null;
        $free_calls = isset($data['data']['attributes']['api']['free_calls']) ? (int) $data['data']['attributes']['api']['free_calls'] : null;

        if ($credits === null && $free_calls === null) {
            return new \WP_Error('removebg_unexpected_response', __('Unexpected response from remove.bg.', 'wp-smart-image-resize'));
        }

        return [
            'credits'    => $credits,
            'free_calls' => $free_calls,
        ];
    }

    /**
     * Extract a human-readable error message from a remove.bg error response.
     *
     * @param string $body
     * @param int    $status
     *
     * @return string
     */
    private static function extract_error_message($body, $status)
    {
        $data = json_decode($body, true);

        if (isset($data['errors'][0]['title'])) {
            return $data['errors'][0]['title'];
        }

        /* translators: %d: HTTP status code returned by the remove.bg API. */
        return sprintf(__('remove.bg API returned HTTP %d.', 'wp-smart-image-resize'), $status);
    }

    /**
     * Build a multipart/form-data request body for the given file.
     *
     * @param string $file_path
     * @param string $boundary
     *
     * @return string
     */
    private static function build_multipart_body($file_path, $boundary)
    {
        $contents = file_get_contents($file_path);
        $filename = wp_basename($file_path);

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"size\"\r\n\r\n";
        $body .= "auto\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"image_file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $contents . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return $body;
    }
}
