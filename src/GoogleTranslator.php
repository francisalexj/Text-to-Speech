<?php

include('Tokens/GoogleTokenGenerator.php');

/**
* Google Text to Speech translator API.
*/
class GoogleTranslator
{
	private $Token;

	private $StringMaxLength = 100;

	private $httpOptions = array();

	private $TotalConversionCount = 1;

	private $ConversionString = "";

	private $ConversionPosition = 0;

	private $Baseurl = "https://translate.google.com/translate_tts";

	private $QueryString = "";

	private $OutputFileName = "";

	private $Response = array(
		'status' => "",
		'message' => "",
		'filepath' => ""
	);
	
	function __construct()
	{
		$this->tokener = new GoogleTokenGenerator();

		$this->setHttpOptions();
	}

	/*
	* Generate Google Text to Speech function
	*/
	function TextToSpeech($text){
		// split texts into multiple chunks
		$Texts = $this->SplitText($text);

		$this->TotalConversionCount = count($Texts);

		$OutputFile = $this->CreateFileInstance();
		if(!$OutputFile){
			goto end;
		}

		foreach ($Texts as $key => $value) {

			$this->ConversionString = $value;
			$this->ConversionPosition = $key;
			
			$this->GenerateToken($value);

			$this->setHttpOptions();

			$this->QueryString = $this->GenerateQueryString();

			$this->GetResponse($OutputFile);

		}

		$this->CloseFileInstance($OutputFile);

		if($this->Response['status']!==false){
			$this->Response['status'] = true;
			$this->Response['message'] = "";
			$this->Response['filepath'] = realpath($this->OutputFileName);
		}

		end:

		return $this->Response;
	}

	/*
	* Generate Google Translate Token
	*/
	function GenerateToken($text){

		$this->Token = $this->tokener->generateToken($text);

	}

	function SplitText($text){

		$text = preg_replace('/[^A-Za-z0-9\. -]/', '', $text);

		$splitted_array = wordwrap($text, $this->StringMaxLength, "{|}", true);
		$splitted_array = explode("{|}", $splitted_array);
		$splitted_array = array_filter($splitted_array);

		return $splitted_array;		
	}

	function setHttpOptions(){

		$this->httpOptions['ie'] = "UTF-8";
		$this->httpOptions['q'] = str_replace(' ', '%20', $this->ConversionString);
		$this->httpOptions['tl'] = "en";
		$this->httpOptions['sl'] = "en";
		$this->httpOptions['total'] = $this->TotalConversionCount;
		$this->httpOptions['idx'] = $this->ConversionPosition;
		$this->httpOptions['textlen'] = strlen($this->ConversionString);
		$this->httpOptions['tk'] = $this->Token;
		$this->httpOptions['client'] = "t";

	}

	function GenerateQueryString(){
		$query = [];

		foreach ($this->httpOptions as $key => $value) {
			$query[] = "{$key}={$value}";
		}

		return implode("&", $query);
	}

	function CreateFileInstance(){

		$file = fopen($this->OutputFileName, 'w');

		if($file && is_resource($file)){
			return $file;
		}

		$this->Response['status'] = false;
		$this->Response['message'] = "Output file could not created";
		return false;
	}

	function CloseFileInstance($file){
		fclose($file);
	}

	function SetOutputFileName($fileName){
		$this->OutputFileName = $fileName;
	}

	function GetResponse($fileInstance){
		$data = $this->CurlRequestHandle();

		if($data) {
			fwrite($fileInstance, $data);
		}
	}

	function CurlRequestHandle(){
		$url = $this->Baseurl."?".$this->QueryString;

		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => 0,
			CURLOPT_HTTPGET => 1,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/62.0.3202.94 Chrome/62.0.3202.94 Safari/537.36',
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_REFERER => 'https://translate.google.com/',
		);
		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		if(!empty($info['http_code']) && $info['http_code']!=200){
			echo "<pre>";
			print_r($info);
			$this->Response['status'] = false;
			$this->Response['message'] = "Google api request failed";
			return false;
		}

		return $output;
	}

}