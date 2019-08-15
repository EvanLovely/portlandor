<?php

namespace Drupal\portland_migrations\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\media\Entity\Media;

/**
 * The MigrateLinkedMedia plugin parses content and looks for images and links to documents.
 * If found, the source URL is used to download and store the file as a media entity, and the
 * link or image tag is converted to an entity-embed element.
 *
 * @MigrateProcessPlugin(
 *   id = "migrate_body_content_and_linked_media"
 * )
 */
class MigrateBodyContentAndLinkedMedia extends ProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    preg_match_all('/<a [^>]+>|<img [^>]+>/i', $value, $downloaded_file);
    if (!empty($downloaded_file[0])) {
      $dom = Html::load($value);
      $xpath = new \DOMXPath($dom);

      $download_dir_uri = $this->prepareDownloadDirectory();

      // look for links with an href and save the linked file
      foreach ($xpath->query('//a[@href]|//img[@src]') as $link) {

        // parse url from link; it will be in an href or src attribute.
        $url = $link->getAttribute('href');
        if (is_null($url)) {
          $url = $link->getAttribute('src');
        }

        // if url is empty or null, skip and continue
        if (is_null($url) || $url == "") {
          continue;
        }

        // if url is relative, prefix it with the POG domain; for downloading files,
        // we require fully qualified URLs. but we want to re-link to relative URLs.
        if (substr($url, 0, 1) == "/") {
          $url = "https://www.portlandoregon.gov" . $url;
        }

        // skip external links and leave the link tag alone
        $internal_link = $this->isInternalLink($url);
        if (!$internal_link) continue;

        // build filename/uri
        $filename = $this->buildPogFilename($url);
        // if buildPogFilename returns false, that means either the URL didn't have a
        // Content-Disposition header (not a binary file), or the URL returned 404.
        if ($filename === false) {
          continue;
        }
        $destination_uri = $download_dir_uri . "/" . $filename;

        $media_type = $this->getMediaType($filename);

        // create file if it doesn't exist. if it does exist, load existing.
        if (!file_exists($destination_uri)) {
          try {
            $downloaded_file = system_retrieve_file($url, $destination_uri, TRUE);
          }
          catch (Exception $e) {
            $message = "Error occurred while trying to download URL target at " . $url . ". Exception: " . $e->getMessage();
            \Drupal::logger('portland_migrations')->notice($message);
          }
        } else {
          $downloaded_file = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $destination_uri]);
          if (is_array($downloaded_file)) {
            $downloaded_file = reset($downloaded_file);
          }
        }

        // if ($downloaded_file === false) {
        //   // the file could not be retrieved from the file system, which indicates it
        //   // doesn't exist. the most likely reason is that the url was a 404.
        //   $message = "Error occurred while trying to open file saved from " . $url . ". Most likely reason is a 404 error.";
        //   \Drupal::logger('portland_migrations')->notice($message);
        //   continue;
        // }

        // NOTE: The following processing is unnecessary since we can now get the original filename
        // and exclude non-binary files.

        // process the file: 
        // delete it if it's not a type we want, or rename it with a more descriptive filename
        // and appropriate extension. when we saved the raw file from POG, it was saved with
        // whatever name was in the URL (typically the content id).

        // try {
        //   $absolute_path = \Drupal::service("file_system")->realpath($destination_uri);
        //   $mime_type = mime_content_type($absolute_path);
        // }
        // catch (Exception $e) {
        //   $message = "Problem getting mime type for file (possible 404) " . $file_url . " at " . $absolute_path;
        //   \Drupal::logger('portland_migrations')->notice($message);
        // }

        // switch ($mime_type) {
        //   case "application/pdf":
        //     $file_info = ["pdf", "document"]; break;
        //   case "application/msword":
        //     $file_info = ["doc", "document"]; break;
        //   case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
        //     $file_info = ["docx", "document"]; break;
        //   case "application/vnd.ms-excel":
        //     $file_info = ["xls", "document"]; break;
        //   case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet":
        //     $file_info = ["xlsx", "document"]; break;
        //   case "application/vnd.ms-powerpoint":
        //     $file_info = ["ppt", "document"]; break;
        //   case "application/vnd.openxmlformats-officedocument.presentationml.presentation":
        //     $file_info = ["pptx", "document"]; break;
        //   case "image/jpeg":
        //     $file_info = ["jpg", "image"]; break;
        //   case "image/gif":
        //     $file_info = ["gif", "image"]; break;
        //   case "image/png":
        //     $file_info = ["png", "image"]; break;
        //   case "text/html":
        //     $file_info = ["html", null]; break;
        //   default:
        //     $file_info = null; break;
        // }

        // TODO: How are we handling links to HTML pages in legacy site? Keep legacy URL?
        // // TODO: if url is relative and mime type is html, do we need to update the url to be from POG, or just keep it relative?
        // if ($file_info[0] == "html" && $internal_link) {
        //   $link->setAttribute("href", $url);
        // }

        // // if ext is html, delete the downloaded file and continue the loop. We'll leave the link as it is.
        // if ($file_info[0] == "html") {
        //   if (isset($downloaded_file) && $downloaded_file) {
        //     $downloaded_file->delete();
        //   }
        //   continue;
        // }

        // // if ext is a type we have not defined, log it, delete the file if it exists, and continue the loop.
        // if (is_null($file_info)) {
        //   if (!isset($mime_type) || !$mime_type || $mime_type == "") {
        //     $mime_type = "Undefined mime type";
        //   }
        //   $message = "Undefined download encountered in migration: " . $mime_type . "<br>File path: " . $absolute_path . "<br>From URL: " . $url;
        //   \Drupal::logger('portland_migrations')->notice($message);
        //   if (isset($downloaded_file) && $downloaded_file) {
        //     $downloaded_file->delete();
        //   }
        //   continue;
        // }

        // // use link text as file name.
        // $link_text = $link->textContent;
        // $filename = $link_text;
        // if (function_exists('transliterate_filenames_transliteration')) {
        //   $filename = transliterate_filenames_transliteration($link_text, $source_langcode);
        // }

        // // don't let filenames start with "link-" or "link-to-"
        // if (substr($filename, 0, 8) == "link-to-") {
        //   $filename = substr($filename, 8, strlen($filename));
        // }
        // if (substr($filename, 0, 5) == "link-") {
        //   $filename = substr($filename, 5, strlen($filename));
        // }

        // TODO: It's possible that we could have two different files with the same name, since
        // we're naming them based on the link text. There's no good way to determine the file
        // name from the URL since most if the time it's just the content id. How big of a problem
        // is this? Perhaps we should use incrememtal numbering in a test and reivew the duplicated
        // files.

        // // rename file if renamed file doesn't alredy exist. append content id on end of filename
        // // to help differentiate between different files that might have the same link text.
        $file = \Drupal\file\Entity\File::load($downloaded_file->fid->value);
        // $new_filename = $folder_name . "/" . $filename . $content_id . "." . $file_info[0];
        // $stream_wrapper = \Drupal::service('file_system')->uriScheme($file->getFileUri());
        // $new_filename_uri = "{$stream_wrapper}://{$new_filename}";
        
        // if (!file_exists($new_filename_uri)) {
        //   $move_result = file_move($file, $new_filename_uri);
        // } else {
        //   // it exists, append to the filename to make it unique
        //   // first see if appended filename exists
        //   $exists = true;
        //   $try_filename = $new_filename_uri;
        //   $safety = 0;
        //   while ($exists == true) {
        //     $try_filename = $try_filename . "-copy";
        //     if (!file_exists($try_filename)) {
        //       // if appended filename doesn't exist, create it
        //       $move_result = file_move($file, $try_filename);
        //       $exists = false;
        //     }
        //     $safety = $safety + 1;
        //     if ($safety > 25) {
        //       $exists = false;
        //     }
          // }

        //   // renamed file already exists. delete temp file, load existing renamed file, and 
        //   // reference that in the embed tag.
        //   $file->delete();
        //   $existing_files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $folder_uri . '/' . $filename . $content_id . '.' . $file_info[0]]);
        //   $file = reset($existing_files); // should only ever be 1 file returned
        // }

        // if ($file == false) {
        //   $halt = true;
        // }

        // create media entity for file (image or document)
        if ($media_type == "document") {
          $media = Media::create([
            'bundle' => 'document',
            'uid' => 1,
            'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
            'name' => $link_text,
            'status' => 1,
            'field_document' => [
              'target_id' => $file->id()
            ],
          ]);
        } else if ($media_type == "image") {
          $media = Media::create([
            'bundle' => 'image',
            'uid' => 1,
            'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
            'name' => $link_text,
            'status' => 1,
            'image' => [
              'target_id' => $file->id()
            ],
          ]);
        }
        $media->save();
        $media->setPublished(TRUE)
              ->save();
        $uuid = $media->uuid();

        // replace <a> or <img> with <entity-embed>
        $node = $dom->createElement("drupal-entity");
        $newnode = $dom->appendChild($node);
        $newnode->setAttribute("data-entity-type", "media");
        $newnode->setAttribute("data-entity-uuid", $uuid);
        $newnode->setAttribute("data-langcode", "en");
        if ($file_info[1] == "document") {
          $newnode->setAttribute("data-embed-button", "document_browser");
          $newnode->setAttribute("data-entity-embed-display", "view_mode:media.embedded");
        } else {
          $newnode->setAttribute("data-embed-button", "image_browser");
          $newnode->setAttribute("data-entity-embed-display", "media_image");
        }
        $link->parentNode->replaceChild($newnode, $link);
      }
      $output = Html::serialize($dom);
      $value = $output;
    }

    return $value;
  }

  protected function prepareDownloadDirectory() {
    // prepare download directory
    $folder_name = date("Y-m") ;
    $folder_uri = file_build_uri($folder_name);
    $public_path = \Drupal::service('file_system')->realpath(file_default_scheme() . "://");
    $download_path = $public_path . "/" . $folder_name;
    $dir = file_prepare_directory($download_path, FILE_CREATE_DIRECTORY);
    return $folder_uri;
  }

  protected function isInternalLink($url) {
    $url_host = parse_url($url, PHP_URL_HOST);
    // if host is anything but www.portlandoregon.gov or www.portlandonline.gov,
    // leave the link alone and continue. we don't want to download and embed
    // external link targets.
    if ($url_host != "www.portlandoregon.gov" && $url_host != "www.portlandonline.gov") {
      return false;
    } else {
      return true;
    }
  }

  protected function buildPogFilename($url) {
    // get content id from URL, might be like /bts/38249 or /image.cfm?id=38249 or /shared/cfm/slb.cfm?id=38249 or /auditor/29194?a=256111
    // this is appended to filename to make sure it's unique.
    $content_id = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
    if (strtolower($content_id) == "image.cfm" || strtolower($content_id == "slb.cfm")) {
      $querystr = parse_url($url, PHP_URL_QUERY);
      $queries = array();
      parse_str($querystr, $queries);
      $content_id = $queries['id'];
    } else {
      $querystr = parse_url($url, PHP_URL_QUERY);
      $queries = array();
      parse_str($querystr, $queries);
      if (isset($queries['a'])) {
        $content_id = $queries['a'];
      }
      // need to re-get file
    }

    // get name from Content-Disposition header
    $headers = get_headers($url, 1);
    if (!isset($headers['Content-Disposition'])) {
      return false;
    }
    $content_disposition = $headers['Content-Disposition']; //"inline; filename="BCP_ENB_1.03EX.pdf""
    $parts = preg_split("/; /", $content_disposition);
    $filename_parts = preg_split("/=/", $parts[1]);
    $filename = preg_replace("/\"/", "", $filename_parts[1]);
    $basename = pathinfo($filename, PATHINFO_BASENAME);
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $basename = preg_replace("/\.".$extension."/", "", $filename);

    if ($content_id == "image" || $content_id == "slb") {
      return $basename . "." . $extension;
    }

    return $basename . "-" . $content_id . "." . $extension;
  }

  protected function getMediaType($filename) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    switch ($ext) {
      case "jpg":
      case "jpeg":
      case "png":
      case "gif":
      case "bmp":
      case "tif":
      case "tiff":
        return "image";
        break;
      default:
        return "document";
        break;      
    }
  }

  protected function getPogFileName($url) {
    return $filename;
  }

  protected function testme() {
    return "tested!";
  }
}