<?php

namespace Webigniter\LaravelClient\Libraries;

class WebigniterClient
{
    public mixed $apiData;
    public string $apiKey;
    private array $formData;
    public string $request;
    private bool $bypassMaintenance;
    private array $customData;
    private ?string $subsite;

    function __construct(string $apiKey, array $customData = [], bool $bypassMaintenance = false, bool $isWiPage = true, ?string $subsite = null)
    {
        session_start();
        $this->bypassMaintenance = $bypassMaintenance;

        $segments = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $this->request = $segments ?? '';
        $this->apiKey = $apiKey;
        $this->customData = $customData;
        $this->subsite = $subsite;

        if(!$isWiPage){
            return;
        }

        if($this->request === "/__formProcess"){
            $this->formProcess();
        } elseif($this->request === "/__memberLogin"){
            $this->memberLogin();
        } elseif($this->request === "/__memberLogout"){
            $this->memberLogout();
        } else{
            $this->apiData = $this->apiCall('getContent', ['uri' => $this->request]);
        }

        if(!empty($this->apiData['contentType'])){
            header('Content-type: '.$this->apiData['contentType']);
            echo $this->apiData['data'];
            exit();
        }

        if($this->apiData['status'] == 301){
            $url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/'.$this->apiData['data'];
            header("Location:".$url, true, 301);
        }

        if($this->apiData['status'] == 404){
            http_response_code(404);
            $requestedPage = $this->apiData['message'];

            $webigniter = $this;

            if(file_exists('Views/errors/404.php')){
                include('Views/errors/404.php');
            } else{
                echo "Page not found: ".$requestedPage;
            }

            exit();
        }

        if($this->apiData['status'] != 200){
            echo $this->apiData['message'];
            exit();
        }
    }

    function __destruct()
    {
        unset($_SESSION['flash_data']);
    }

    public function getLayoutFile()
    {
        $data['webigniter'] = $this;

        foreach($this->customData as $variableName => $value){
            $data[$variableName] = $value;
        }

        return view(rtrim($this->apiData['data']['__layoutFile'],'.php'), $data);
    }

    public function getSectionsContent(array $userData = []): void
    {
        $data['webigniter'] = $this;

        foreach($this->customData as $variableName => $value){
            $data[$variableName] = $value;
        }

        foreach($this->apiData['data']['sections'] ?? [] as $sectionName => $sectionData){
            foreach($sectionData as $variableName => $value){
                $data[$variableName] = $value;
            }

            if(key_exists('extraData', $sectionData)){
                foreach($sectionData['extraData'] as $variableName => $value){
                    $data[$variableName] = $value;
                }
            }

            foreach($userData as $variableName => $value){
                $data[$variableName] = $value;
            }

            echo view(rtrim($this->apiData['data']['sections'][$sectionName]['__sectionFile'],'.php'), $data);
        }
    }

    public function getCategoryContent(string $handle): array
    {
        $returnArray = [];

        $dataFields =
            [
                'handle' => $handle,
            ];

        $pages = $this->apiCall('getCategoryContent', $dataFields);

        foreach($pages['pages'] as $key => $page){
            $returnArray[$key]['__created_at'] = $page['__created_at'];
            $returnArray[$key]['__updated_at'] = $page['__updated_at'];
            $returnArray[$key]['__uri'] = $page['__uri'];
            $returnArray[$key]['__name'] = $page['__name'];

            foreach($page['sections'] as $sectionName => $sectionData){
                $returnArray[$key][$sectionName] = $sectionData;
            }
        }

        return $returnArray;
    }

    public function getNavigation(string $handle): array
    {
        if(key_exists($handle, $this->apiData['data']['__navigations'])){
            return $this->apiData['data']['__navigations'][$handle];
        } else{
            die("Navigation ".$handle." not found");
        }
    }

    public function getBreadCrumbs(): array
    {
        return $this->apiData['data']['__breadcrumbs'];
    }

    public function getAnchors(): array
    {
        return $this->apiData['data']['__anchors'];
    }

    public function getSeoSnippet(): string
    {
        return $this->apiData['data']['__seoSnippet'];
    }

    public function getGlobal(string $handle): array
    {
        if(key_exists($handle, $this->apiData['data']['__globals'])){
            return $this->apiData['data']['__globals'][$handle];
        } else{
            die("Global ".$handle." not found or empty");
        }
    }

    public function getDatacollectionEntries(string $handle, bool $unpublished = false, string $orderBy = '', string $orderType = 'ASC', array $filterBy = [], array $filterValue = [], int $limit = 999999): array
    {
        $dataFields =
            [
                'handle' => $handle,
                'unpublished' => $unpublished,
                'orderBy' => $orderBy,
                'orderType' => $orderType,
                'filterBy' => serialize($filterBy),
                'filterValues' => serialize($filterValue),
                'limit' => $limit
            ];

        $datacollectionEntries = $this->apiCall('getDatacollectionEntries', $dataFields);

        if($datacollectionEntries['status'] == 404){
            die("datacollection ".$handle." not found");
        }

        $datacollectionEntriesArray = json_decode($datacollectionEntries['entries'], true);

        return $datacollectionEntriesArray;
    }

    public function formStart(string $handle, ?string $attributes = null, array $presetData = []): ?string
    {
        $dataFields =
            [
                'handle' => $handle,
                'attributes' => $attributes,
                'presetData' => serialize($presetData),
                'original_data' => serialize($this->getFlashData('original_data')),
                'self' => $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
            ];

        $this->formData = $this->apiCall('getForm', $dataFields);
        if($this->formData['status'] == 404){
            return "form ".$handle." not found";
        }

        return $this->formData['formStart'];
    }

    public function formFields(string $handle): ?array
    {
        $dataFields =
            [
                'handle' => $handle
            ];

        if(!$this->formData){
            $this->formData = $this->apiCall('getForm', $dataFields);
        }

        if($this->formData['status'] == 404){
            return [];
        }

        return $this->formData['formFields'];
    }

    public function printFormField(string $field, string $attributes = null): string
    {
        return str_replace('%attr%', $attributes, $field);
    }

    public function formEnd(): string
    {
        return '</form>';
    }

    public function getFlashData(string $key)
    {
        if(key_exists('flash_data', $_SESSION) && key_exists($key, $_SESSION['flash_data'])){
            $flashData = $_SESSION['flash_data'][$key];
            return $flashData;
        }

        return null;
    }

    public static function setFlashData(string $key, $value): void
    {
        $_SESSION['flash_data'][$key] = $value;
    }

    public function getUrlSegment(int $segmentNumber)
    {
        $uriSegments = explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        if(key_exists($segmentNumber, $uriSegments) && $uriSegments[$segmentNumber]){
            return $uriSegments[$segmentNumber];
        }

        return null;
    }

    public function getUrlSegments()
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    public function getPageName(): string
    {
        return $this->apiData['data']['__name'];
    }

    public function getSubsite(): ?string
    {
        return $this->apiData['data']['__subsite'];
    }

    public function getCreatedAt(string $format)
    {
        $dateTime = strtotime($this->apiData['data']['__created_at']);
        return date($format, $dateTime);
    }

    public function getUpdatedAt(string $format)
    {
        $dateTime = strtotime($this->apiData['data']['__updated_at']);
        return date($format, $dateTime);
    }


    public function searchPages($searchQuery, ?array $categoryIds = null): array
    {
        $dataFields = [
            'search_query' => $searchQuery,
            'category_ids' => serialize($categoryIds),
        ];

        return json_decode($this->apiCall('searchPages', $dataFields)['pages'], true);
    }

    public function verifyUserSession(string $extensionName): void
    {
        if(empty($_GET['hash'])){
            die('unauthorized, missing hash');
        }

        if(empty($extensionName)){
            die('unauthorized, missing extension name');
        }

        $dataFields = [
            'hash' => $_GET['hash'],
            'remote_addr' => $_SERVER['REMOTE_ADDR'],
            'extension_name' => $extensionName,
        ];

        $apiResult = $this->apiCall('verifyUserSession', $dataFields);

        if($apiResult['status'] != 200){
            die($apiResult['message']);
        }

        echo $apiResult['snippet'];
    }

    public function getMediaList(string $folderName, string $fileOrderType = '', int $fileLimit = 999, int $fileLimitOffset = 0, bool $showFolders = false, string $folderOrderType = '', int $folderLimit = 999, int $folderLimitOffset = 0): array
    {
        $dataFields =
            [
                'folderName' => urlencode($folderName),
                'fileOrderType' => $fileOrderType,
                'fileLimit' => $fileLimit,
                'fileLimitOffset' => $fileLimitOffset,
                'showFolders' => $showFolders ? 1 : 0,
                'folderOrderType' => $folderOrderType,
                'folderLimit' => $folderLimit,
                'folderLimitOffset' => $folderLimitOffset,
            ];

        return json_decode($this->apiCall('getMediaList', $dataFields)['media'], true);
    }

    public function checkMemberSession(): array
    {
        $hash = null;

        if(!empty($_COOKIE['_loginHash'])){
            $hash = $_COOKIE['_loginHash'];
        } elseif(!empty($_SESSION['_loginHash'])){
            $hash = $_SESSION['_loginHash'];
        }

        if(!$hash){
            return ['status' => 403];
        }

        $data['__ip_address'] = $_SERVER['REMOTE_ADDR'];
        $data['hash'] = $hash;

        return $this->apiCall('checkMemberSession', $data);
    }

    public function memberSave(array $data)
    {
        $memberSaveResult = $this->apiCall('memberSave', $data, 'POST');

        $errors = unserialize($memberSaveResult['errors']);

        if(!empty($errors)){
            $this->setFlashData('errors', $errors);
        }

        $this->setFlashData('success', $memberSaveResult['success']);

        if(!$memberSaveResult['success']){
            return false;
        }

        return true;
    }


    private function memberLogin()
    {
        if(empty($_POST['username']) || empty($_POST['password'])){
            if(!empty($_POST['return_url'])){
                header('location:'.$_POST['return_url']);
            } else{
                echo json_encode(['status' => 403]);
            }
            exit();
        }

        $data = $_POST;
        $data['__ip_address'] = $_SERVER['REMOTE_ADDR'];

        $memberLogin = $this->apiCall('memberLogin', $data, 'POST');

        if($memberLogin['status'] == 200){

            $_SESSION['_loginHash'] = $memberLogin['hash'];

            if(!empty($_POST['remember'])){
                setcookie("_loginHash", $memberLogin['hash']);
            }

            if(!empty($_POST['redirect'])){
                header('location:'.$_POST['redirect']);
            } else{
                echo json_encode(['status' => 200]);
            }
            exit;
        }

        if($memberLogin['status'] == 403){
            if(!empty($_POST['return_url'])){
                header('location:'.$_POST['return_url']);
            } else{
                echo json_encode(['status' => 403]);
            }
            exit();
        }
    }

    private function memberLogout()
    {
        unset($_SESSION['_loginHash']);
        setcookie("_loginHash", "", time() - 3600, "/");

        $redirect = empty($_GET['redirect']) ? '/' : urldecode($_GET['redirect']);

        header("Location: ".$redirect);
        exit();
    }

    private function formProcess()
    {
        if(empty($_POST['website-url_wi'])){
            $this->formsExtender('pre', $_POST['_formHandle']);

            $extraData = [
                '_ip_address' => $_SERVER['REMOTE_ADDR']
            ];

            $postData = array_merge($_POST, $extraData);
            $formProcessData = $this->apiCall('formProcess', $postData, 'POST');

            $this->setFlashData('errors', unserialize($formProcessData['errors']));
            $this->setFlashData('original_data', unserialize($formProcessData['original_data']));
            $this->setFlashData('success', $formProcessData['success']);

            if($formProcessData['success']){
                $this->formsExtender('post', $this->url_title($_POST['_formHandle'], '_', true));
            }
        } else{
            $formProcessData['redirect'] = $_SERVER['HTTP_REFERER'];
        }

        header('location:'.$formProcessData['redirect']);
        exit();
    }

    private function apiCall(string $apiEndpoint, ?array $dataFields = null, string $method = 'GET'): array
    {
        $getValues = null;

        if($method == 'GET' && $dataFields){
            foreach($dataFields as $key => $value){
                $getValues .= $key.'='.urlencode($value).'&';
            }

            if($getValues){
                $getValues = '?'.rtrim($getValues, '&');
            }
        }

        $handler = curl_init('https://api.webigniter.net/'.$apiEndpoint.$getValues);

        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, false);

        if($method == 'POST' && $dataFields){
            $postData = http_build_query($dataFields);
            curl_setopt($handler, CURLOPT_POSTFIELDS, $postData);
        }

        $headers[] = 'Secret: '.$this->apiKey;
        $headers[] = 'BypassMaintenance: '.$this->bypassMaintenance ? 1 : 0;
        $headers[] = 'Origin: '.$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'];
        if($this->subsite){
            $headers[] = 'Subsite: '.$this->subsite;
        }

        curl_setopt($handler, CURLOPT_HTTPHEADER, $headers);
        $apiOutput = curl_exec($handler);

        return json_decode($apiOutput, true);
    }

    private function formsExtender(string $type, $formHandle): void
    {
        $formsExtendLibrary = dirname(__FILE__).'/Extending/FormsExtend.php';
        if(is_file($formsExtendLibrary)){
            require_once($formsExtendLibrary);
            $formsExtender = new FormsExtend($_POST, $this);
            $methodName = $type.'_'.$formHandle;

            if(method_exists($formsExtender, $methodName)){
                if(!$formsExtender->$methodName()){
                    $this->setFlashData('success', false);
                    $this->setFlashData('original_data', $_POST);
                    header('location:'.$_POST['_self']);
                    exit();
                }
            }
        }
    }

    /** HELPERS */

    private function url_title(string $str, string $separator = '-', bool $lowercase = false): string
    {
        $qSeparator = preg_quote($separator, '#');

        $trans = [
            '&.+?;' => '',
            '[^\w\d\pL\pM _-]' => '',
            '\s+' => $separator,
            '('.$qSeparator.')+' => $separator,
        ];

        $str = strip_tags($str);

        foreach($trans as $key => $val){
            $str = preg_replace('#'.$key.'#iu', $val, $str);
        }

        if($lowercase === true){
            $str = strtolower($str);
        }

        return trim(trim($str, $separator));
    }
}