<?php
/**
 * LICENSE
 *
 * Copyright © 2016-2018 Teclib'
 * Copyright © 2010-2018 by the FusionInventory Development Team.
 *
 * This file is part of Flyve MDM Plugin for GLPI.
 *
 * Flyve MDM Plugin for GLPI is a subproject of Flyve MDM. Flyve MDM is a mobile
 * device management software.
 *
 * Flyve MDM Plugin for GLPI is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * Flyve MDM Plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with Flyve MDM Plugin for GLPI. If not, see http://www.gnu.org/licenses/.
 * ------------------------------------------------------------------------------
 * @author    Thierry Bugier
 * @copyright Copyright © 2018 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 0.1.0
 */
class PluginFlyvemdmPackage extends CommonDBTM {

   /**
    * @var string $rightname name of the right in DB
    */
   static $rightname = 'flyvemdm:package';

   /**
    * @var bool $usenotepad enable notepad for the itemtype (GLPi < 0.85)
    */
   protected $usenotepad = true;

   /**
    * @var bool $usenotepad enable notepad for the itemtype (GLPi >=0.85)
    */
   protected $usenotepadRights = true;

   /**
    * Localized name of the type
    * @param integer $nb number of item in the type (default 0)
    * @return string
    */
   public static function getTypeName($nb = 0) {
      return _n('Package', 'Packages', $nb, 'flyvemdm');
   }

   /**
    * Returns the picture file for the menu
    * @return string the menu picture
    */
   public static function getMenuPicture() {
      return 'fa-gear';
   }

   /**
    * @see CommonGLPI::defineTabs()
    */
   public function defineTabs($options = []) {
      $tab = [];
      $this->addDefaultFormTab($tab);
      $plugin = new Plugin();
      if ($plugin->isActivated('orion')) {
         $this->addStandardTab(PluginOrionReport::class, $tab, $options);
      }
      $this->addStandardTab(Notepad::class, $tab, $options);
      $this->addStandardTab(Log::class, $tab, $options);

      return $tab;
   }

   /**
    * Returns the tab name of this itemtype, depending on the itemtype on which it will be displayed
    * If the tab shall not display then returns an empty string
    * @param CommonGLPI $item on which the tab will show
    * @param integer $withtemplate template mode for $item : 0 = no template - 1 = edit template - 2 = from template
    * @return string
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      switch ($item->getType()) {
         case 'Software' :
            return _n('Package Flyve MDM', 'Packages Flyve MDM', Session::getPluralNumber(),
               'flyvemdm');

      }
      return '';
   }

   /**
    *  Display the content of the tab provided by this itemtype
    * @param CommonGLPI $item
    * @param integer $tabnum
    * @param integer $withtemplate
    * @return bool
    */
   public static function displayTabContentForItem(
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ) {
      if ($item->getType() == 'Software') {
         self::showForSoftware($item);
         return true;
      }
   }

   /**
    * Display a form to view, create or edit
    * @param integer $ID ID of the item to show
    * @param array $options
    */
   public function showForm($ID, array $options = []) {
      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      $twig = plugin_flyvemdm_getTemplateEngine();
      $fields = $this->fields;
      $objectName = autoName(
         $this->fields['name'], 'name',
         (isset($options['withtemplate']) && $options['withtemplate'] == 2),
         $this->getType(), -1
      );
      $fields['name'] = Html::autocompletionTextField(
         $this, 'name',
         ['value' => $objectName, 'display' => false]
      );
      $this->addExtraFileInfo();
      if ($this->isNewID($ID)) {
         $fields['filesize'] = '';
      }
      $data = [
         'withTemplate' => (isset($options['withtemplate']) && $options['withtemplate'] ? '*' : ''),
         'canUpdate'    => (!$this->isNewID($ID)) && ($this->canUpdate() > 0) || $this->isNewID($ID),
         'isNewID'      => $this->isNewID($ID),
         'package'      => $fields,
         'upload'       => Html::file(['name' => 'file', 'display' => false]),
      ];
      echo $twig->render('package.html.twig', $data);

      $this->showFormButtons($options);
   }

   /**
    * @see CommonDBTM::addNeededInfoToInput()
    */
   public function addNeededInfoToInput($input) {
      $input['entities_id'] = $_SESSION['glpiactive_entity'];

      return $input;
   }

   /**
    * @see CommonDBTM::prepareInputForAdd()
    */
   public function prepareInputForAdd($input) {
      // Name must not be populated
      if (!Session::isCron()) {
         unset($input['package_name']);
      }

      // Find the added file
      $preparedFile = $this->prepareFileUpload();
      if (!$preparedFile) {
         return false;
      }

      if (!$this->isFileUploadValid($preparedFile['filename'])) {
         return false;
      }

      if (!isset($input['entities_id'])) {
         $input['entities_id'] = $_SESSION['glpiactive_entity'];
      }
      try {
         $uploadedFile = $preparedFile['uploadedFile'];
         $input['filename'] = 'flyvemdm/package/' . $input['entities_id'] . '/' . uniqid() . '_' . basename($uploadedFile);
         $destination = GLPI_PLUGIN_DOC_DIR . '/' . $input['filename'];
         $this->createEntityDirectory(dirname($destination));
         if (rename($uploadedFile, $destination)) {
            $input['dl_filename'] = basename($uploadedFile);
         } else {
            $this->logErrorIfDirNotWritable($destination);
            Session::addMessageAfterRedirect(__('Unable to save the file', 'flyvemdm'));
            $input = false;
         }
      } catch (Exception $e) {
         // Ignore exceptions for now
         Session::addMessageAfterRedirect(__('Could not parse the APK file', 'flyvemdm'));
         $input = false;
      }

      return $input;
   }

   /**
    * (non-PHPdoc)
    * @see CommonDBTM::prepareInputForUpdate()
    */
   public function prepareInputForUpdate($input) {
      if (!Session::isCron()) {
         unset($input['package_name']);
      }
      // Find the added file
      $preparedFile = $this->prepareFileUpload();

      if ($preparedFile && is_array($preparedFile)) {
         try {
            if (!$this->isFileUploadValid($preparedFile['filename'])) {
               return false;
            }
            $uploadedFile = $preparedFile['uploadedFile'];
            $input['filename'] = 'flyvemdm/package/' . $this->fields['entities_id'] . "/" . uniqid() . "_" . basename($uploadedFile);
            $destination = GLPI_PLUGIN_DOC_DIR . '/' . $input['filename'];
            $this->createEntityDirectory(dirname($destination));
            if (rename($uploadedFile, $destination)) {
               $filename = pathinfo($destination, PATHINFO_FILENAME);
               $input['dl_filename'] = basename($destination);
               if ($filename != $this->fields['filename']) {
                  unlink(GLPI_PLUGIN_DOC_DIR . "/" . $this->fields['filename']);
               }
            } else {
               $this->logErrorIfDirNotWritable($destination);
               Session::addMessageAfterRedirect(__('Unable to save the file', "flyvemdm"));
               $input = false;
            }
         } catch (Exception $e) {
            // Ignore exceptions for now
            $input = false;
         }
      }
      return $input;
   }

   /**
    * Actions done after the getFromDB method
    */
   public function post_getFromDB() {
      // Check the user can view this itemtype and can view this item
      if ($this->canView() && $this->canViewItem()) {
         $this->addExtraFileInfo();
         if (isAPI()
            && (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/octet-stream'
               || isset($_GET['alt']) && $_GET['alt'] == 'media')) {
            $this->sendFile(); // and terminate script
         }
      }
   }

   public function post_addItem() {
      $this->createOrionReport();
   }

   /**
    * Create a file analysis task with the Orion plugin
    */
   private function createOrionReport() {
      $plugin = new Plugin();
      if ($plugin->isActivated('orion')) {
         $orionReport = new PluginOrionReport();
         $orionReport->add([
            'itemtype' => $this->getType(),
            'items_id' => $this->getID(),
         ]);
      }
   }

   /**
    * @see CommonDBTM::post_updateItem()
    */
   public function post_updateItem($history = 1) {
      if (!isset($this->oldvalues['filename'])) {
         return;
      }

      $itemtype = $this->getType();
      $itemId = $this->getID();

      $task = new PluginFlyvemdmTask();
      $tasks = $task->find("`itemtype`='$itemtype' AND `items_id`='$itemId'");
      foreach ($tasks as $taskId => $taskRow) {
         $notifiableType = $taskRow['itemtype_applied'];
         $notifiable = new $notifiableType();
         $notifiableId = $taskRow['items_id_applied'];
         if ($notifiable->getFromDB($notifiableId)) {
            Toolbox::logInFile('php-errors',
               "Plugin Flyvemdm : Could not find notifiable id = '$notifiableId'");
            continue;
         }
         if ($task->getFromDB($taskId)) {
            $task->publishPolicy($notifiable);
            $task->createTaskStatuses($notifiable);
         }
      }

      // File updated, then scan it again
      $this->createOrionReport();
   }

   /**
    * @see CommonDBTM::pre_deleteItem()
    */
   public function pre_deleteItem() {
      $task = new PluginFlyvemdmTask();
      return $task->deleteByCriteria([
         'itemtype' => $this->getType(),
         'items_id' => $this->getID(),
      ]);
   }

   /**
    * @see CommonDBTM::post_purgeItem()
    */
   public function post_purgeItem() {
      $filename = GLPI_PLUGIN_DOC_DIR . "/" . $this->fields['filename'];
      if (is_writable($filename)) {
         unlink($filename);
      }
   }

   /**
    * Create a directory
    * @param string $dir
    */
   protected function createEntityDirectory($dir) {
      if (!is_dir($dir)) {
         @mkdir($dir, 0770, true);
      }
   }

   /**
    * @return array
    */
   public function getSearchOptionsNew() {
      $tab = parent::getSearchOptionsNew();

      $tab[] = [
         'id'            => '2',
         'table'         => $this->getTable(),
         'field'         => 'id',
         'name'          => __('ID'),
         'massiveaction' => false,
         'datatype'      => 'number',
      ];

      $tab[] = [
         'id'            => '3',
         'table'         => $this->getTable(),
         'field'         => 'alias',
         'name'          => __('alias'),
         'massiveaction' => false,
         'datatype'      => 'string',
      ];

      $tab[] = [
         'id'            => '4',
         'table'         => $this->getTable(),
         'field'         => 'version',
         'name'          => __('version'),
         'massiveaction' => false,
         'datatype'      => 'string',
      ];

      $tab[] = [
         'id'            => '5',
         'table'         => $this->getTable(),
         'field'         => 'icon',
         'name'          => __('icon'),
         'massiveaction' => false,
         'datatype'      => 'image',
      ];

      $tab[] = [
         'id'                 => '6',
         'table'              => 'glpi_entities',
         'field'              => 'completename',
         'name'               => __('Entity'),
         'datatype'           => 'dropdown'
      ];

      return $tab;
   }

   /**
    * Get the download URL for the application
    * @return boolean|string
    */
   public function getFileURL() {
      $config = Config::getConfigurationValues('flyvemdm', ['deploy_base_url']);
      $deployBaseURL = $config['deploy_base_url'];

      if ($deployBaseURL === null) {
         return false;
      }

      $URL = $deployBaseURL . '/package/' . $this->fields['filename'];
      return $URL;
   }

   /**
    * Sends a file
    */
   protected function sendFile() {
      $streamSource = GLPI_PLUGIN_DOC_DIR . "/" . $this->fields['filename'];

      if (!file_exists($streamSource) || !is_file($streamSource)) {
         header("HTTP/1.0 404 Not Found");
         exit(0);
      }

      $size = $this->fields['filesize'];
      $begin = 0;
      $end = $size - 1;
      $mimeType = 'application/octet-stream';
      $time = date('r', filemtime($streamSource));

      $fileHandle = @fopen($streamSource, 'rb');
      if (!$fileHandle) {
         header("HTTP/1.0 500 Internal Server Error");
         exit(0);
      }

      if (isset($_SERVER['HTTP_RANGE'])) {
         $matches = null;
         if (preg_match('/bytes=\h*(\d+)?-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
            if (!empty($matches[1])) {
               $begin = intval($matches[1]);
            }
            if (!empty($matches[2])) {
               $end = min(intval($matches[2]), $end);
            }
         }
      }

      // seek to the begining of the range
      $currentPosition = $begin;
      if (fseek($fileHandle, $begin, SEEK_SET) < 0) {
         header("HTTP/1.0 500 Internal Server Error");
         exit(0);
      }

      // send headers to ensure the client is able to detect an corrupted download
      // example : less bytes than the expected range
      header("Expires: Mon, 26 Nov 1962 00:00:00 GMT");
      header('Pragma: private'); /// IE BUG + SSL
      header('Cache-control: private, must-revalidate'); /// IE BUG + SSL
      header("Content-disposition: attachment; filename=\"" . $this->fields['dl_filename'] . "\"");
      header("Content-type: $mimeType");
      header("Last-Modified: $time");
      header('Accept-Ranges: bytes');
      header('Content-Length:' . ($end - $begin + 1));
      header("Content-Range: bytes $begin-$end/$size");
      header("Content-Transfer-Encoding: binary\n");
      header('Connection: close');

      $httpStatus = 'HTTP/1.0 200 OK';
      if ($begin > 0 || $end < $size - 1) {
         $httpStatus = 'HTTP/1.0 206 Partial Content';
      }
      header($httpStatus);

      // Sends bytes until the end of the range or connection closed
      while (!feof($fileHandle) && $currentPosition < $end && (connection_status() == 0)) {
         // allow a few seconds to send a few KB.
         set_time_limit(10);
         $content = fread($fileHandle, min(1024 * 16, $end - $currentPosition + 1));
         if ($content === false) {
            header("HTTP/1.0 500 Internal Server Error", true); // Replace previously sent headers
            exit(0);
         }
         print $content;
         flush();
         $currentPosition += 1024 * 16;
      }

      exit(0);
   }

   /**
    * get Cron description parameter for this class
    *
    * @param $name string name of the task
    *
    * @return array of string
    **/
   static function cronInfo($name) {

      switch ($name) {
         case 'ParseApplication' :
            return ['description' => __('Parse an application to find metadata', 'flyvemdm')];
      }
   }


   /**
    * Launches parsing of applciation files
    *
    * @see PluginFlyvemdmPackage::parseApplication()
    *
    * @param CronTask $crontask
    *
    * @return integer >0 means done, < 0 means not finished, 0 means nothing to do
    */
   public static function cronParseApplication(CronTask $crontask) {
      global $DB;

      $cronStatus = 0;

      $request = [
         'FROM'  => static::getTable(),
         'WHERE' => [
            'AND' => [
               'parse_status' => 'pending',
            ],
         ],
         'LIMIT' => 10,
      ];
      foreach ($DB->request($request) as $data) {
         $package = new static();
         $package->getFromDB($data['id']);
         if ($package->parseApplication()) {
            $crontask->addVolume(1);
         }
      }

      $cronStatus = 1;
      return $cronStatus;
   }

   /**
    * Analyzes an application (APK or UPK) to collect metadata
    *
    * @return boolean true if success, false otherwise
    */
   private function parseApplication() {
      $destination = GLPI_PLUGIN_DOC_DIR . '/' . $this->fields['filename'];
      $fileExtension = pathinfo($destination, PATHINFO_EXTENSION);
      if ($fileExtension == 'apk') {
         try {
            $apk = new \ApkParser\Parser($destination);
         } catch (Exception $e) {
            Toolbox::logInFile('php-errors', 'plugin Flyve MDM: ' . $e->getMessage() . PHP_EOL);
            return false;
         }
      } else if ($fileExtension == 'upk') {
         $upkParser = new PluginFlyvemdmUpkparser($destination);
         $apk = $upkParser->getApkParser();
         if (!($apk instanceof \ApkParser\Parser)) {
            $this->update([
               'id'           => $this->fields['id'],
               'parse_status' => 'failed',
            ]);
            return false;
         }
      } else {
         return false;
      }
      $input = [];
      $manifest = $apk->getManifest();
      $iconResources = $apk->getResources($manifest->getApplication()->getIcon());
      $apkLabel = $apk->getResources($manifest->getApplication()->getLabel());
      $input['icon'] = base64_encode(stream_get_contents($apk->getStream($iconResources[0])));
      $input['package_name'] = $manifest->getPackageName();
      $input['version'] = $manifest->getVersionName();
      $input['version_code'] = $manifest->getVersionCode();
      if ((!isset($input['alias'])) || (strlen($input['alias']) == 0)) {
         $input['alias'] = $apkLabel[0]; // Get the first item
      }

      $input['id'] = $this->fields['id'];
      $input['parse_status'] = 'parsed';
      return $this->update($input);
   }

   /**
    * Deletes the packages related to the entity
    * @param CommonDBTM $item
    */
   public function hook_entity_purge(CommonDBTM $item) {
      $package = new static();
      $package->deleteByCriteria(['entities_id' => $item->getField('id')], 1);
   }

   /**
    * @param string $destination filename with full path to be written
    */
   private function logErrorIfDirNotWritable($destination) {
      $dirname = dirname($destination);
      if (!is_writable($dirname)) {
         Toolbox::logInFile('php-errors',
            "Plugin Flyvemdm : Directory '$dirname' is not writeable");
      }
   }

   /**
    * @param string $filename
    * @return bool
    */
   private function isFileUploadValid($filename) {
      if (!isset($filename) || !$filename) {
         Session::addMessageAfterRedirect(__('File uploaded without name', "flyvemdm"));
         return false;
      }

      $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
      if (!in_array($fileExtension, ['apk', 'upk'])) {
         Session::addMessageAfterRedirect(__('Only APK and UPK files are allowed', 'flyvemdm'));
         return false;
      }

      return true;
   }

   /**
    * Find the added file
    * @return array|bool
    */
   private function prepareFileUpload() {
      if (!isAPI()) {
         // from GLPI UI
         $postFile = $_POST['_file'][0];
         if (!isset($postFile) || !is_string($postFile)) {
            Session::addMessageAfterRedirect(__('No file uploaded', "flyvemdm"));
            return false;
         }
         $actualFilename = $postFile;
         $uploadedFile = GLPI_TMP_DIR . "/" . $postFile;
      } else {
         // from API
         if (!isset($_FILES['file'])) {
            Session::addMessageAfterRedirect(__('No file uploaded', "flyvemdm"));
            return false;
         }

         $fileError = $_FILES['file']['error'];
         if (!$fileError == 0) {
            if (!$fileError == 4) {
               Session::addMessageAfterRedirect(__('File upload failed', "flyvemdm"));
            }
            return false;
         }

         $fileName = $_FILES['file']['name'];
         $fileTmpName = $_FILES['file']['tmp_name'];
         $destination = GLPI_TMP_DIR . '/' . $fileName;
         if (is_readable($fileTmpName) && !is_readable($destination)) {
            // Move the file to GLPI_TMP_DIR
            if (!is_dir(GLPI_TMP_DIR)) {
               Session::addMessageAfterRedirect(__("Temp directory doesn't exist"), false,
                  ERROR);
               return false;
            }
         }

         $actualFilename = $fileName;
         $uploadedFile = $destination;
      }

      return ['uploadedFile' => $uploadedFile, 'filename' => $actualFilename];
   }

   /**
    * Gets the filename of a package
    * @return string|NULL the path to tie file relative to the DOC ROOT àf GLPI
    */
   public function getFilename() {
      if (!$this->isNewItem()) {
         return $this->fields['filename'];
      }

      return null;
   }

   /**
    * Adds extra fields to the itemType
    */
   protected function addExtraFileInfo() {
      $filename = GLPI_PLUGIN_DOC_DIR . '/' . $this->fields['filename'];
      $isFile = is_file($filename);
      $this->fields['filesize'] = ($isFile) ? fileSize($filename) : 0;
      $this->fields['mime_type'] = ($isFile) ? mime_content_type($filename) : '';
   }

   /**
    * Define how to display a specific value in search result table
    *
    * @param  string $field   Name of the field as define in $this->getSearchOptions()
    * @param  string $values  The value as it is stored in DB
    * @param  array  $options Options (optional)
    * @return string          Value to be displayed
    */
   public static function getSpecificValueToDisplay($field, $values, array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'icon':
            if (!isAPI()) {
               $output = '<img style="height: 14px" src="data:image/png;base64,'. $values[$field] .'">';
               return $output;
            }
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }
}
