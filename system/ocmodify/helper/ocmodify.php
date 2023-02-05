<?php
/* Requirements */
function ocmCheckRequirements() {
  $errors = array();

  // Mbstring
  if (!in_array('mbstring', get_loaded_extensions())) {
    $errors['mbstring'] = OCModify::$instance->language->get('error_requirement_mbstring');
  }

  // Curl
  if (!in_array('curl', get_loaded_extensions()) && !function_exists('curl_version') && !extension_loaded('curl')) {
    $errors['curl'] = OCModify::$instance->language->get('error_requirement_curl');
  }

  // Mmcrypt
  #xtop | disabled
  /*if (!in_array('mcrypt', get_loaded_extensions())) {
    $errors['mcrypt'] = OCModify::$instance->language->get('error_requirement_mcrypt');
  }*/

  // Zip
  if (!in_array('zip', get_loaded_extensions())) {
    $errors['zip'] = OCModify::$instance->language->get('error_requirement_zip');
  }

  // vqMod
  if (version_compare(VERSION, '2', '<')) {
    if (!class_exists('VQMod')) {
      $errors['vqmod'] = OCModify::$instance->language->get('error_requirement_vqmod');
    } else {
      $vqm = new ReflectionClass('VQMod');
      if ($vqm->isAbstract()) {
        if (version_compare(VQMod::$_vqversion, '2.4', '<')) {
          $errors['vqmod'] = OCModify::$instance->language->get('error_requirement_vqmod_version');
        }
      } else {
        $rp = $vqm->getProperty('_vqversion');
        if (!$rp->isStatic() || $rp->isPrivate()) {
          $rp->setAccessible(true);
          if (version_compare($rp->getValue(new VQMod()), '2.4', '<')) {
            $errors['vqmod'] = OCModify::$instance->language->get('error_requirement_vqmod_version');
          }
        }
      }
    }
  }

  // Messages
  foreach ($errors as $error) {
    OCModify::$instance->message->add('error', $error);
  }

  // Fatal output errors on frontend
  if ($errors && !OCM_IS_ADMIN && !defined('OCM_IS_CRON')) {
    ocmMessageBox(OCModify::$instance->message->get('error'), OCModify::$instance->language->get('heading_title_requirement'));
  }

  return !$errors;
}

/* Files */
function ocmFileGet($url, $data = array(), $headers = array()) {
  $result = '';
  if (extension_loaded('curl')) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, ini_get('open_basedir') ? 0 : 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($curl, CURLOPT_TIMEOUT, 3);
    curl_setopt($curl, CURLOPT_USERAGENT, (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0'));
    if ($data) {
      if (is_array($data) || is_object($data)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, ocmBuildQuery($data));
      } elseif (is_string($data)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      }
    }
    if ($headers) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    $result = curl_exec($curl);

    curl_close($curl);

    return $result;
  }

  return $result;
}

function ocmCleanDir($dir, $remove_root = true) {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (is_dir($dir . "/" . $object)) {
          ocmCleanDir($dir . "/" . $object);
        } else {
          @unlink($dir . "/" . $object);
        }
      }
    }
    if ($remove_root) {
      @rmdir($dir);
    }
  }
}

function ocmRealPath($path) {
  return str_replace('\\', '/', realpath($path)) . '/';
}

function ocmGetModifiedFile($file) {
  if (version_compare(VERSION, '2', '>=')) {
    $modification = (modification($file));
  } else {
    $modification = $file;
  }

  if (!is_file($modification)) {
    $modification = $file;
  }

  global $vqmod;
  $modification = class_exists('VQMod') ? (!empty($vqmod) ? $vqmod->modCheck($modification) : VQMod::modCheck($modification)) : $modification;

  if (is_file($modification)) {
    return $modification;
  } else {
    return $file;
  }
}

function ocmGetRealFile($file) {
  $file = str_replace('\\', '/', $file);
  if (version_compare(VERSION, '2', '>=')) {
    $file = str_replace(str_replace('\\', '/', realpath(DIR_MODIFICATION)) . '/', '', $file);
  }
  $file = str_replace(str_replace('\\', '/', realpath(DIR_SYSTEM . '../')) . '/', '', $file);
  $file = preg_replace('#^vqmod/vqcache/vq2-(.*)#', '${1}', $file);
  $file = preg_replace('#system_(storage_)?modification_#', '', $file);

  $parts = explode('_', $file);

  $file = '';
  $delemiter = '';
  foreach ($parts as $part) {
    $path = $file . $delemiter . $part;
    if (file_exists(DIR_SYSTEM . '../' . $path)) {
      $delemiter = '/';
      $file = $path;
      continue;
    } else {
      $delemiter = '_';
      $file = $path;
      continue;
    }
  }

  return $file;
}

/* Store */
function ocmGetStores() {
  $current_link = (ocmIsSecure() ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  $current_link = str_replace('&amp;', '&', $current_link);
  $current_link = preg_replace('#(\?|&)store_id=\d+#', '', $current_link);

  $stores = array();
  $stores[0] = array('store_id' => 0, 'name' => OCModify::$instance->config->get('config_name'));
  $query = OCModify::$instance->db->query("SELECT * FROM " . DB_PREFIX . "store");
  if ($query->num_rows) {
    foreach ($query->rows as $row) {
      $stores[$row['store_id']] = array('store_id' => $row['store_id'], 'name' => $row['name']);
    }
  }

  foreach ($stores as $store_id => &$store) {
    $store['url'] = $current_link . ($store_id ? '&store_id=' . $store['store_id'] : '');
    $store['name'] = (strlen($store['name']) > 32) ? utf8_substr($store['name'], 0, 32) . '...' : $store['name'];
  }

  uasort($stores, function($a) {
    return $a['store_id'] != OCM_STORE_ID;
  });

  return $stores;
}

function ocmGetBreadcrumbs($breadcrumbs, $stores = array()) {
  $html = '';
  if ($breadcrumbs) {
    $html .= '<ul class="breadcrumb">' . "\n";
    foreach ($breadcrumbs as $breadcrumb) {
      $html .= '<li><a href="' . $breadcrumb['href'] . '">' . $breadcrumb['text'] . '</a></li>' . "\n";
    }
    if ($stores) {
      $html .= '<li class="pull-right"><div class="dropdown">' . "\n";
      $html .= '<button class="btn btn-sm btn-info dropdown-toggle" type="button" id="select-store" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . $stores[OCM_STORE_ID]['name'] . ' <span class="caret"></span></button>' . "\n";
      $html .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="select-store">' . "\n";
      foreach ($stores as $store_id => $store) {
        $html .= '<li><a href="' . $store['url'] . '">' . $store['name'] . '</a></li>' . "\n";
      }
      $html .= '</ul>' . "\n";
      $html .= '</div></li>' . "\n";
    }
    $html .= '</ul>' . "\n";
  }

  return $html;
}

/* Modification */
function ocmCheckMods() {
  if (OCM_STORE_ID != 0) {
    return false;
  }

  if (version_compare(VERSION, '2', '>=') && (!ocmIsLogged(OCModify::$instance) || (isset($_GET['route']) && preg_match('#^(extension|marketplace)/modification/refresh$#', $_GET['route'])))) {
    return false;
  }

  libxml_use_internal_errors(true);

  $refresh_required = false;

  $modules = glob(OCM_DIR . 'extension/*', GLOB_ONLYDIR);

  if ($modules) {
    $action_required = false;
    $install_framework = false;

    // Check if framework modification required
    foreach ($modules as $i => $module) {
      $code = basename($module);
      if ($code == 'ocmodify') {
        $framework = $module;
        unset($modules[$i]);
      } elseif (OCModify::$instance->config->get($code . '_status')) {
        $install_framework = true;
      }
    }

    // Add framework moto the end of array
    $modules[] = $framework;

    // Install / uninstall modifications
    foreach ($modules as $module) {
      $code = basename($module);
      if (version_compare(VERSION, '2', '>=')) {
        $files = glob($module . '/*.ocmod.xml');
      } else {
        $files = glob($module . '/*.vqmod.xml');
      }
      foreach ($files as $file) {
        if (($code == 'ocmodify' && $install_framework) || OCModify::$instance->config->get($code . '_status')) {
          if (OCM_IS_ADMIN && version_compare(VERSION, '2.0.1', '>=')) {
            if (is_file(OCM_DIR_XML . basename($file))) {
              $xml_installed = simplexml_load_file(OCM_DIR_XML . basename($file));
              $xml_uploaded = simplexml_load_file($file);
              if ($xml_uploaded && !empty($xml_uploaded->version)) {
                if (!$xml_installed || empty($xml_installed->version) || (string )$xml_installed->version != (string )$xml_uploaded->version || filemtime($file) > filemtime(OCM_DIR_XML . basename($file))) {
                  $refresh_required = true;
                  OCModify::$instance->extension->installMods($code, false);
                }
              }
            } else {
              $refresh_required = true;
              OCModify::$instance->extension->installMods($code, false);
            }
          } elseif (OCM_IS_ADMIN && version_compare(VERSION, '2', '>=')) {
            $query = OCModify::$instance->db->query("SELECT * FROM " . DB_PREFIX . "modification WHERE `name` = '" . OCModify::$instance->db->escape(basename($file, '.ocmod.xml')) . "'");
            if ($query->num_rows) {
              $xml_installed = simplexml_load_string($query->row['code']);
              $xml_uploaded = simplexml_load_file($file);
              if ($xml_uploaded && !empty($xml_uploaded->version)) {
                if (!$xml_installed || empty($xml_installed->version)) {
                  if (isset($query->row['version'])) {
                    $xml_installed = new \stdClass();
                    $xml_installed->version = $query->row['version'];
                    $xml_installed->date_added = strtotime($query->row['date_added']);
                  }
                }
                if (!$xml_installed || empty($xml_installed->version) || (string )$xml_installed->version != (string )$xml_uploaded->version || filemtime($file) > $xml_installed->date_added) {
                  $refresh_required = true;
                  OCModify::$instance->extension->installMods($code, false);
                }
              }
            } else {
              $refresh_required = true;
              OCModify::$instance->extension->installMods($code, false);
            }
          } elseif (version_compare(VERSION, '2', '<')) {
            if (is_file(OCM_DIR_XML . basename($file, '.vqmod.xml') . '.xml')) {
              $xml_installed = simplexml_load_file(OCM_DIR_XML . basename($file, '.vqmod.xml') . '.xml');
              $xml_uploaded = simplexml_load_file($file);
              if ($xml_uploaded && !empty($xml_uploaded->version)) {
                if (!$xml_installed || empty($xml_installed->version) || (string )$xml_installed->version != (string )$xml_uploaded->version || filemtime($file) > filemtime(OCM_DIR_XML . basename($file, '.vqmod.xml') . '.xml')) {
                  $refresh_required = true;
                  OCModify::$instance->extension->installMods($code, false);
                }
              }
            } else {
              $refresh_required = true;
              OCModify::$instance->extension->installMods($code, false);
            }
          }
        } else {
          if (version_compare(VERSION, '2.0.1', '>=')) {
            if (is_file(OCM_DIR_XML . basename($file))) {
              $refresh_required = true;
              unlink(OCM_DIR_XML . basename($file));
            }
          } elseif (version_compare(VERSION, '2', '>=')) {
            $query = OCModify::$instance->db->query("SELECT * FROM " . DB_PREFIX . "modification WHERE `name` = '" . OCModify::$instance->db->escape(basename($file, '.ocmod.xml')) . "'");
            if ($query->num_rows) {
              $refresh_required = true;
              OCModify::$instance->db->query("DELETE FROM " . DB_PREFIX . "modification WHERE `name` = '" . OCModify::$instance->db->escape(basename($file, '.ocmod.xml')) . "'");
            }
          } else {
            if (is_file(OCM_DIR_XML . basename($file, '.vqmod.xml') . '.xml')) {
              $refresh_required = true;
              unlink(OCM_DIR_XML . basename($file, '.vqmod.xml') . '.xml');
            }
          }
        }
      }
    }
  }

  if ($refresh_required || !ocmCheckModCache()) {
    if (ocmRefreshMods()) {
      ocmReloadCurrentPage();
    }
  }

  return true;
}

function ocmCheckModCache() {
  $error = false;
  if (version_compare(VERSION, '2', '>=')) {
    if (!glob(OCM_DIR_OCMOD . '*', GLOB_ONLYDIR)) {
      $error = true;
    } else {
      foreach ((array)glob(OCM_DIR_XML . '*.ocmod.xml') as $file) {
        if (filemtime($file) > filemtime(OCM_DIR_OCMOD . 'system')) {
          $error = true;
          break;
        }
      }
    }
  }
  if (defined('OCM_DIR_VQMOD') && is_dir(OCM_DIR_VQMOD)) {
    if (!glob(OCM_DIR_VQMOD . '*.cache')) {
      $error = true;
    } else {
      foreach ((array)glob(OCM_DIR_VQMOD . '*.cache') as $cache) {
        foreach ((array)glob(OCM_DIR_XML . '*.xml') as $file) {
          if (filemtime($file) > filemtime($cache)) {
            $error = true;
            break;
          }
        }
      }
    }
  }

  return !$error;
}

function ocmRefreshMods() {
  $status = false;
  if (version_compare(VERSION, '2', '>=')) {
    ocmCleanDir(OCM_DIR_OCMOD, false);
    if (OCM_ADMIN_FOLDER && ocmIsLogged(OCModify::$instance)) {
      if (OCM_IS_ADMIN) {
        $url = OCModify::$instance->url->link((version_compare(VERSION, '3', '>=') ? 'marketplace' : 'extension') . '/modification/refresh', '', 'HTTPS');
      } else {
        $url = HTTPS_SERVER . OCM_ADMIN_FOLDER . '/index.php?route=' . (version_compare(VERSION, '3', '>=') ? 'marketplace' : 'extension') . '/modification/refresh&token=' . ocmGetToken();
      }

      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
      curl_setopt($curl, CURLOPT_TIMEOUT, 10);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, ini_get('open_basedir') ? 0 : 1);
      curl_setopt($curl, CURLOPT_USERAGENT, OCModify::$instance->request->server['HTTP_USER_AGENT']);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

      if (OCModify::$instance->request->cookie) {
        $cookie = '';
        foreach (OCModify::$instance->request->cookie as $key => $value) {
          $cookie .= "$key=$value;";
        }
        curl_setopt($curl, CURLOPT_COOKIE, $cookie);
      }

      if (empty($_SESSION)) {
        @session_start();
      }

      $session_data = $_SESSION;

      if (!empty($_SESSION)) {
        session_write_close();
      }

      curl_exec($curl);

      if (curl_errno($curl)) {
        $status = false;
      } else {
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpcode == 200) {
          $status = true;
        } else {
          $status = false;
        }
      }

      curl_close($curl);

      if (empty($_SESSION)) {
        @session_start();
      }

      $_SESSION = $session_data;

      if (!$status) {
        if (isset($_GET['debug'])) {
          trigger_error('Error: Could not automatically refresh modification cache!');
        }

        if (OCM_IS_ADMIN) {
          OCModify::$instance->message->add('warning', OCModify::$instance->language->get('error_modification'));
        }
      }
    }
  }

  if (defined('OCM_DIR_VQMOD') && is_dir(OCM_DIR_VQMOD)) {
    if (ocmVQmodFlush()) {
      $status = true;
    }
  }

  return $status;
}

function ocmVQmodFlush() {
  // Clean
  foreach ((array)glob(OCM_DIR_VQMOD . '{*.cache}', GLOB_BRACE) as $file) {
    unlink($file);
  }

  // Check
  $caches = glob(OCM_DIR_VQMOD . '{*.cache}', GLOB_BRACE);

  return empty($caches);
}

/* Installation */
function ocmCheckInstalls() {
  foreach ((array)glob(OCM_DIR . 'extension/*', GLOB_ONLYDIR) as $extension_directory) {
    $extension_code = basename($extension_directory);
    if ($extension_code != 'ocmodify') {
      ocmCheckInstall($extension_code);
    }
  }
}

function ocmGetInstall($extension_code) {
  $install = array();

  if (defined('DEMO_STORE')) {
    if (is_file(DEMO_STORE . 'extension/' . $extension_code . '.json')) {
      $install = (array)json_decode(file_get_contents(DEMO_STORE . 'extension/' . $extension_code . '.json'), true);
    }
  } else {
    if (is_file(OCM_DIR . 'extension/' . $extension_code . '/install.json')) {
      $install = (array)json_decode(file_get_contents(OCM_DIR . 'extension/' . $extension_code . '/install.json'), true);
    }
  }

  return $install;
}

function ocmCheckInstall($extension_code) {
  // Install info
  $install = ocmGetInstall($extension_code);

  // DB info
  $query = OCModify::$instance->db->query("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `code` = '" . $extension_code . "'");

  if (($install && !$query->num_rows) || (!$install && $query->num_rows)) {
    $version = null;
    if (is_file(OCM_DIR . 'extension/' . $extension_code . '/config.php')) {
      include(OCM_DIR . 'extension/' . $extension_code . '/config.php');
    }
    if (!$install || $version != $install['version']) {
      // Execute install
      ocmInstall($extension_code);
    }
  } elseif (!$install || !$query->num_rows) {
    //ocmUninstall($extension_code);
  }
}

function ocmInstall($extension_code) {
  // Get current install
  $install = ocmGetInstall($extension_code);

  // Check directories
  if (defined('DEMO_STORE')) {
    if (!is_dir(DEMO_STORE . 'extension')) {
      mkdir(DEMO_STORE . 'extension', 0755, true);
    }
  }

  // Init default config
  $version = null;
  if (is_file(OCM_DIR . 'extension/' . $extension_code . '/config.php')) {
    include(OCM_DIR . 'extension/' . $extension_code . '/config.php');
  }

  // Execute install.php script
  if (is_file(OCM_DIR . 'extension/' . $extension_code . '/install.php')) {
    $installed = require_once(OCM_DIR . 'extension/' . $extension_code . '/install.php');
  } else {
    $installed = true;
  }

  // Set meta information
  if ($installed !== false) {
    if (defined('DEMO_STORE')) {
      file_put_contents(DEMO_STORE . 'extension/' . $extension_code . '.json', json_encode(array('version' => $version, 'time' => time())));
    } else {
      file_put_contents(OCM_DIR . 'extension/' . $extension_code . '/install.json', json_encode(array('version' => $version, 'time' => time())));
    }
  }

  // Message to user-end
  if ($install) {
    //OCModify::$instance->message->add(OCModify::$instance->language->get('text_success_update'));
  }
}

function ocmUninstall($extension_code) {
  // Execute uninstall.php script
  if (is_file(OCM_DIR . 'extension/' . $extension_code . '/uninstall.php')) {
    require_once(OCM_DIR . 'extension/' . $extension_code . '/uninstall.php');
  }

  // Remove db information
  OCModify::$instance->db->query("DELETE FROM `" . DB_PREFIX . "extension` WHERE `code` = '" . $extension_code . "'");

  // Remove meta information
  if (defined('DEMO_STORE')) {
    if (is_file(DEMO_STORE . 'extension/' . $extension_code . '.json')) {
      unlink(DEMO_STORE . 'extension/' . $extension_code . '.json');
    }
  } else {
    if (is_file(OCM_DIR . 'extension/' . $extension_code . '/install.json')) {
      unlink(OCM_DIR . 'extension/' . $extension_code . '/install.json');
    }
  }
}

/* Request */
function ocmGetRoute($route) {
  if (version_compare(VERSION, '2.3', '>=')) {
    return preg_replace('#^((?:admin|catalog)/(?:controller|model|language/(?:[a-z-]+))/)?(?:extension/)?(analytics|captcha|dashboard|feed|fraud|module|openbay|payment|report|shipping|theme|total)(/\w+)#', '${1}extension/${2}${3}', $route);
  } else {
    return preg_replace('#^((?:admin|catalog)/(?:controller|model|language/(?:[a-z-]+))/)?(?:extension/)?(analytics|captcha|dashboard|feed|fraud|module|openbay|payment|report|shipping|theme|total)(/\w+)#', '${1}${2}${3}', $route);
  }
}

function ocmGetRouteByFile($file) {
  return preg_replace('#^' . preg_quote(DIR_APPLICATION . 'controller/') . '(.*)\.php$#', '${1}', $file);
}

function ocmBuildQuery($args, $delimiter = '&', $equal = '=') {
  $parts = array();
  foreach ($args as $key => $value) {
    if ($value) {
      $parts[] = $key . $equal . $value;
    } else {
      $parts[] = $key;
    }
  }

  return implode($delimiter, $parts);
}

function ocmIsAjax() {
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    return true;
  } else {
    return false;
  }
}

function ocmIsSecure() {
  $isSecure = false;
  if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == '1')) {
    $isSecure = true;
  } elseif (isset($_SERVER['HTTP_X_FORWARDED_HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_HTTPS'] == 'on' || $_SERVER['HTTP_X_FORWARDED_HTTPS'] == '1')) {
    $isSecure = true;
  } elseif ((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && ($_SERVER['HTTP_X_FORWARDED_SSL'] == 'on' || $_SERVER['HTTP_X_FORWARDED_SSL'] == '1'))) {
    $isSecure = true;
  } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
    $isSecure = true;
  }

  return $isSecure;
}

function ocmReloadCurrentPage() {
  if (ocmIsAjax()) {
    if (OCModify::$instance && !empty(OCModify::$instance->request->get['route']) && !preg_match('#/(install|uninstall)$#', OCModify::$instance->request->get['route'])) {
      //exit(json_encode(array('messages' => OCModify::$instance->message->get())));
    }
  } else {
    $current_link = (ocmIsSecure() ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    header('Location: ' . str_replace('&amp;', '&', $current_link));
  }
}

/* Core */
function ocmIsLogged() {
  $result = false;
  if (isset(OCModify::$instance->session->data['user_id']) && isset(OCModify::$instance->session->data[ocmGetTokenKey()])) {
    if (defined('OCM_IS_LOGGED')) {
      $result = true;
    } else {
      $query = OCModify::$instance->db->query("SELECT * FROM " . DB_PREFIX . "user WHERE user_id = '" . (int)OCModify::$instance->session->data['user_id'] . "' AND status = '1'");
      if ($query->num_rows) {
        $result = true;
        define('OCM_IS_LOGGED', true);
      }
    }
  }

  return $result;
}

function ocmIsLoggedIn() {
  return (OCM_IS_ADMIN && ocmIsLogged(OCModify::$instance) && isset(OCModify::$instance->request->get[ocmGetTokenKey()]) && OCModify::$instance->request->get[ocmGetTokenKey()] == OCModify::$instance->session->data[ocmGetTokenKey()]);
}

function ocmGetToken() {
  if (isset(OCModify::$instance->session->data[ocmGetTokenKey()])) {
    return OCModify::$instance->session->data[ocmGetTokenKey()];
  } else {
    return '';
  }
}

function ocmGetTokenKey() {
  return version_compare(VERSION, '3', '>=') ? 'user_token' : 'token';
}

function ocmMessageBox($messages, $title, $template = 'default.tpl') {
  header('HTTP/1.1 503 Service Temporarily Unavailable');
  header('Status: 503 Service Temporarily Unavailable');
  header('Retry-After: 300');
  require_once(OCM_DIR . 'storage/template/' . $template);
  exit();
}

function ocmGetPHPBin() {
  $bin = '';
  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    if ($phpBin = ocmExec("where php-cgi")) {
      $bin = $phpBin;
    } elseif ($phpBin = ocmExec("where php")) {
      $bin = $phpBin;
    } elseif (defined('PHP_BINARY')) {
      $bin = PHP_BINARY;
    } else {
      $bin = '[PHP_BINARY]';
    }
  } else {
    if (version_compare(PHP_VERSION, '7', '>=')) {
      if (defined('PHP_BINARY')) {
        $bin = PHP_BINARY;
      } elseif ($phpBin = ocmExec("which php")) {
        $bin = $phpBin;
      }
    } elseif (version_compare(PHP_VERSION, '5', '>=')) {
      if ($phpBin = ocmExec("which php5-cli")) {
        $bin = $phpBin;
      } elseif ($phpBin = ocmExec("which php5-cgi")) {
        $bin = $phpBin;
      } elseif ($phpBin = ocmExec("which php-cli")) {
        $bin = $phpBin;
      } elseif ($phpBin = ocmExec("which php")) {
        $bin = $phpBin;
      } elseif (defined('PHP_BINARY')) {
        $bin = PHP_BINARY;
      }
    }
  }
  if (empty($bin)) {
    return 'php';
  } else {
    return $bin;
  }
}

function ocmExec($cmd) {
  $disabled = explode(',', ini_get('disable_functions'));
  if (!in_array('exec', $disabled)) {
    exec($cmd);
  }
}

function ocmGetIP() {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    return $_SERVER['HTTP_CLIENT_IP'];
  } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    return $_SERVER['HTTP_X_REAL_IP'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else {
    return $_SERVER['REMOTE_ADDR'];
  }
}