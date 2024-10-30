<?php
/**
 * @package Hivo Connector
 */

if (!defined('ABSPATH')) {
  die('You are not allowed to call this page directly.');
}

if (!class_exists('Hivo_Connector_Upload_Api')) {
  class Hivo_Connector_Upload_Api {

    /**
     * Constructor
     * @since 0.0.1
     */
    public function __construct() {
      add_action('rest_api_init', [$this, 'init_api']);
    }

    /**
     * Defines the API
     * @since 0.0.1
     */
    public function init_api() {
      register_rest_route('hivo-connector/v1', '/add-assets', array(
        'methods' => 'POST',
        'callback' => [$this, 'upload_hivo_asset'],
        'args' => array(),
        'permission_callback' => function () {
          return current_user_can('upload_files');
        }
      ));
    }

    /**
     * API callback function
     * @since 0.0.1
     */
    public function upload_hivo_asset($req) {
      try {

        // Constants.
        $id_regex = '/^[23456789ABCDEFGHJKLMNPQRSTWXYZabcdefghijkmnopqrstuvwxyz]{17}$/';
        $token_regex = '/^[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw048]=$/';

        // Check for HIVO authentication headers.
        $user_id = $req->get_header('x-user-id');
        $auth_token = $req->get_header('x-auth-token');

        // Verify authentication headers.
        if (!$user_id) {
          return new WP_Error('bad_request', 'Bad Request', array('status' => 400));
        }
        if (preg_match($id_regex, $user_id) === false) {
          return new WP_Error('bad_request', 'Bad Request', array('status' => 400));
        }
        if (!$auth_token) {
          return new WP_Error('bad_request', 'Bad Request', array('status' => 400));
        }
        if (preg_match($token_regex, $auth_token) === false) {
          return new WP_Error('bad_request', 'Bad Request', array('status' => 400));
        }

        // Check for args.
        $params = $req->get_params();
        $upload_id = $params['uploadId'];
        $version = $params['version'];
        $name = $params['name'];

        // Check the args.
        if (!$upload_id) {
          return new WP_Error('bad_request', 'Bad Request', array('status' => 400));
        }
        if (preg_match($id_regex, $upload_id) === false) {
          return new WP_Error('bad_request', 'Bad Request', array('status' => 400));
        }
        if (!$version) {
          return new WP_Error('bad_request', 'Bad Request', array('status' => 400));
        }

        // Try and get the asset contents.
        $contents = file_get_contents(
          'https://app.hivo.com.au/api/uploads/' . rawurlencode($upload_id) . '/download?dl=0&version=' . rawurlencode($version),
          false,
          stream_context_create([
            "http" => [
              "method" => "GET",
              "header" => "x-user-id: " . $user_id . "\r\n" . "x-auth-token: " . $auth_token . "\r\n"
            ]
          ])
        );
        if (!$contents) {
          return new WP_Error('not_found', 'Not Found', array('status' => 404));
        }

        // Get file path.
        $mime_type = $this->_get_file_mime_type($http_response_header);
        $ext = $this->_get_mime_type_extension($mime_type);
        $upload_path = $this->_get_random_upload_path($ext);

        // Write to file.
        $save_file = fopen($upload_path, 'w');
        fwrite($save_file, $contents);
        fclose($save_file);

        // Create attachment.
        $attach_id = wp_insert_attachment(
          array(
            'post_mime_type' => $mime_type ? $mime_type : 'application/octet-stream',
            'post_title' => $name ? pathinfo($name)['filename'] . ($ext ? '.'.$ext : '') : basename($upload_path),
            'post_content' => '',
            'post_status' => 'inherit'
          ),
          $upload_path
        );

        // Generate metadata and sub-sizes.
        wp_update_attachment_metadata(
          $attach_id,
          wp_generate_attachment_metadata($attach_id, $upload_path)
        );

        return new WP_REST_Response("Success", 200);

      } catch (Exception $e) {

        // Something went wrong.
        return new WP_Error('internal_server_error', 'Internal Server Error', array('status' => 500));
      }
    }

    /**
     * Gets mime type for file contents
     * @since 0.0.1
     */
    public function _get_file_mime_type($response_headers) {

      // Get content-type header.
      $pattern = "/^content-type\s*:\s*(.*)$/i";
      $headers = array_values(preg_grep($pattern, $response_headers));

      // Get mime-type from header.
      if (count($headers) > 0 && preg_match($pattern, $headers[0], $match) !== false) {
        return $match[1];
      }

      return '';
    }

    /**
     * Gets extension given a mime type
     * @since 0.0.1
     */
    public function _get_mime_type_extension($mime_type) {

      if (!$mime_type) {
        return '';
      }

      $mime_map = [
        'video/3gpp2' => '3g2',
        'video/3gp' => '3gp',
        'video/3gpp' => '3gp',
        'application/x-compressed' => '7zip',
        'audio/x-acc' => 'aac',
        'audio/ac3' => 'ac3',
        'application/postscript' => 'ai',
        'audio/x-aiff' => 'aif',
        'audio/aiff' => 'aif',
        'audio/x-au' => 'au',
        'video/x-msvideo' => 'avi',
        'video/msvideo' => 'avi',
        'video/avi' => 'avi',
        'application/x-troff-msvideo' => 'avi',
        'application/macbinary' => 'bin',
        'application/mac-binary' => 'bin',
        'application/x-binary' => 'bin',
        'application/x-macbinary' => 'bin',
        'image/bmp' => 'bmp',
        'image/x-bmp' => 'bmp',
        'image/x-bitmap' => 'bmp',
        'image/x-xbitmap' => 'bmp',
        'image/x-win-bitmap' => 'bmp',
        'image/x-windows-bmp' => 'bmp',
        'image/ms-bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'application/bmp' => 'bmp',
        'application/x-bmp' => 'bmp',
        'application/x-win-bitmap' => 'bmp',
        'application/cdr' => 'cdr',
        'application/coreldraw' => 'cdr',
        'application/x-cdr' => 'cdr',
        'application/x-coreldraw' => 'cdr',
        'image/cdr' => 'cdr',
        'image/x-cdr' => 'cdr',
        'zz-application/zz-winassoc-cdr' => 'cdr',
        'application/mac-compactpro' => 'cpt',
        'application/pkix-crl' => 'crl',
        'application/pkcs-crl' => 'crl',
        'application/x-x509-ca-cert' => 'crt',
        'application/pkix-cert' => 'crt',
        'text/css' => 'css',
        'text/x-comma-separated-values' => 'csv',
        'text/comma-separated-values' => 'csv',
        'application/vnd.msexcel' => 'csv',
        'application/x-director' => 'dcr',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/x-dvi' => 'dvi',
        'message/rfc822' => 'eml',
        'application/x-msdownload' => 'exe',
        'video/x-f4v' => 'f4v',
        'audio/x-flac' => 'flac',
        'video/x-flv' => 'flv',
        'image/gif' => 'gif',
        'application/gpg-keys' => 'gpg',
        'application/x-gtar' => 'gtar',
        'application/x-gzip' => 'gzip',
        'application/mac-binhex40' => 'hqx',
        'application/mac-binhex' => 'hqx',
        'application/x-binhex40' => 'hqx',
        'application/x-mac-binhex40' => 'hqx',
        'text/html' => 'html',
        'image/x-icon' => 'ico',
        'image/x-ico' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'text/calendar' => 'ics',
        'application/java-archive' => 'jar',
        'application/x-java-application' => 'jar',
        'application/x-jar' => 'jar',
        'image/jp2' => 'jp2',
        'video/mj2' => 'jp2',
        'image/jpx' => 'jp2',
        'image/jpm' => 'jp2',
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'application/x-javascript' => 'js',
        'application/json' => 'json',
        'text/json' => 'json',
        'application/vnd.google-earth.kml+xml' => 'kml',
        'application/vnd.google-earth.kmz' => 'kmz',
        'text/x-log' => 'log',
        'audio/x-m4a' => 'm4a',
        'application/vnd.mpegurl' => 'm4u',
        'audio/midi' => 'mid',
        'application/vnd.mif' => 'mif',
        'video/quicktime' => 'mov',
        'video/x-sgi-movie' => 'movie',
        'audio/mpeg' => 'mp3',
        'audio/mpg' => 'mp3',
        'audio/mpeg3' => 'mp3',
        'audio/mp3' => 'mp3',
        'video/mp4' => 'mp4',
        'video/mpeg' => 'mpeg',
        'application/oda' => 'oda',
        'audio/ogg' => 'ogg',
        'video/ogg' => 'ogg',
        'application/ogg' => 'ogg',
        'application/x-pkcs10' => 'p10',
        'application/pkcs10' => 'p10',
        'application/x-pkcs12' => 'p12',
        'application/x-pkcs7-signature' => 'p7a',
        'application/pkcs7-mime' => 'p7c',
        'application/x-pkcs7-mime' => 'p7c',
        'application/x-pkcs7-certreqresp' => 'p7r',
        'application/pkcs7-signature' => 'p7s',
        'application/pdf' => 'pdf',
        'application/octet-stream' => 'pdf',
        'application/x-x509-user-cert' => 'pem',
        'application/x-pem-file' => 'pem',
        'application/pgp' => 'pgp',
        'application/x-httpd-php' => 'php',
        'application/php' => 'php',
        'application/x-php' => 'php',
        'text/php' => 'php',
        'text/x-php' => 'php',
        'application/x-httpd-php-source' => 'php',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'application/powerpoint' => 'ppt',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.ms-office' => 'ppt',
        'application/msword' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/x-photoshop' => 'psd',
        'image/vnd.adobe.photoshop' => 'psd',
        'audio/x-realaudio' => 'ra',
        'audio/x-pn-realaudio' => 'ram',
        'application/x-rar' => 'rar',
        'application/rar' => 'rar',
        'application/x-rar-compressed' => 'rar',
        'audio/x-pn-realaudio-plugin' => 'rpm',
        'application/x-pkcs7' => 'rsa',
        'text/rtf' => 'rtf',
        'text/richtext' => 'rtx',
        'video/vnd.rn-realvideo' => 'rv',
        'application/x-stuffit' => 'sit',
        'application/smil' => 'smil',
        'text/srt' => 'srt',
        'image/svg+xml' => 'svg',
        'application/x-shockwave-flash' => 'swf',
        'application/x-tar' => 'tar',
        'application/x-gzip-compressed' => 'tgz',
        'image/tiff' => 'tiff',
        'text/plain' => 'txt',
        'text/x-vcard' => 'vcf',
        'application/videolan' => 'vlc',
        'text/vtt' => 'vtt',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/wav' => 'wav',
        'application/wbxml' => 'wbxml',
        'video/webm' => 'webm',
        'audio/x-ms-wma' => 'wma',
        'application/wmlc' => 'wmlc',
        'video/x-ms-wmv' => 'wmv',
        'video/x-ms-asf' => 'wmv',
        'application/xhtml+xml' => 'xhtml',
        'application/excel' => 'xl',
        'application/msexcel' => 'xls',
        'application/x-msexcel' => 'xls',
        'application/x-ms-excel' => 'xls',
        'application/x-excel' => 'xls',
        'application/x-dos_ms_excel' => 'xls',
        'application/xls' => 'xls',
        'application/x-xls' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xlsx',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
        'text/xsl' => 'xsl',
        'application/xspf+xml' => 'xspf',
        'application/x-compress' => 'z',
        'application/x-zip' => 'zip',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/s-compressed' => 'zip',
        'multipart/x-zip' => 'zip',
        'text/x-scriptzsh' => 'zsh'
      ];

      return isset($mime_map[$mime_type]) === true ? $mime_map[$mime_type] : '';
    }

    /**
     * Gets new random upload path
     * @since 0.0.1
     */
    public function _get_random_upload_path($ext) {

      $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < 9; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
      }

      return wp_upload_dir()['path'] . '/' . date("d-m-Y") . '_' . $randomString . ($ext ? '.'.$ext : '');
    }

  }
}
