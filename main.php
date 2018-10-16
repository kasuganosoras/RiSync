<?php
/**
 *
 *	RiSync Sakura's Mirror 镜像站同步
 *
 *	by Akkariin
 *
 */
class RiSync {
	
	private $server;
	private $storage;
	private $execType;
	
	private function http($url, $post = '', $cookie = '', $returnCookie = 0) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		/** Use ShadowsocksR **/
		//curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);  
		//curl_setopt($curl, CURLOPT_PROXY, "192.168.3.231:2356");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
		curl_setopt($curl, CURLOPT_REFERER, $url);
		if ($post) {
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
		}
		if ($cookie) {
			curl_setopt($curl, CURLOPT_COOKIE, $cookie);
		}
		curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
		curl_setopt($curl, CURLOPT_TIMEOUT, 100);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($curl);
		if (curl_errno($curl)) {
			return curl_error($curl);
		}
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if($code !== 200) {
			$data = false;
		}
		curl_close($curl);
		return $data;
	}
	
	private function download($save, $url, $execType = 0) {
		if(file_exists($save)) {
			return;
		}
		switch($execType) {
			case 0:
				$data = $this->http($url);
				if($data && strlen($data) > 128) {
					@file_put_contents($save, $data);
				} else {
					$this->Println("Download Failed! {$data}");
				}
				break;
			case 1:
				system("curl \"{$url}\" --output \"{$save}\"");
				break;
			case 2:
				system("wget \"{$url}\" -O \"{$save}\"");
				break;
			case 3:
				system("axel -an 16 \"{$url}\" -o \"{$save}\"");
				break;
			default:
				$this->Println("Unknown type: {$execType}");
		}
		
	}
	
	private function Println($str) {
		echo date("[Y-m-d H:i:s") . "] {$str}\n";
	}
	
	private function getServerType() {
		$data = $this->http("https://" . $this->server . "/?action=typelist");
		return json_decode($data, true);
	}
	
	private function getServerList($type) {
		$data = $this->http("https://" . $this->server . "/?action=getlist&version={$type}");
		return json_decode($data, true);
	}
	
	public function setInfo($server, $storage, $execType) {
		$this->server = $server;
		$this->storage = $storage;
		$this->execType = $execType;
	}
	
	public function handle($fileList, $storage) {
		foreach($fileList as $file) {
			if(isset($file['child'])) {
				if(!file_exists("{$storage}/{$file['child']}/")) {
					mkdir("{$storage}/{$file['child']}/", 755, true);
				}
				$this->handle($file['child'], "{$storage}/{$file['child']}/");
			} else {
				$this->Println("Start download {$file['file']}");
				$this->download("{$storage}/{$file['name']}", $file['file'], $this->execType);
			}
		}
	}
	
	public function start() {
		$this->Println("Start sync files...");
		if($this->server == "" || $this->storage == "" || $this->execType == 0) {
			$this->Println("Server or Storage undefined!");
			return false;
		}
		$this->Println("Loading server type list...");
		$typeList = $this->getServerType();
		if(!$typeList) {
			$this->Println("Failed get type list!");
			exit;
		}
		$storage = $this->storage;
		$failed = Array();
		foreach($typeList as $type) {
			if(!file_exists("{$storage}/{$type}/")) {
				mkdir("{$storage}/{$type}/", 755, true);
			}
			$fileList = $this->getServerList($type);
			if(!$fileList) {
				$this->Println("Failed get file list!");
				$failed[] = $type;
				continue;
			}
			$this->handle($fileList, "{$storage}/{$type}/");
			$this->Println("Successful download all file of {$type}");
		}
		$this->Println("RiSync complete!");
		if(count($failed) > 0) {
			foreach($failed as $item) {
				$this->Println("{$item} sync failed!");
			}
		}
	}
}
define("DOWNLOAD_PCUL", 0);	// 使用 PHP curl 下载
define("DOWNLOAD_CURL", 1);	// 调用系统 curl 下载
define("DOWNLOAD_WGET", 2);	// 调用系统 wget 下载
define("DOWNLOAD_AXEL", 3);	// 调用系统 axel 下载
$RiSync = new RiSync();
                 // 镜像源           // 储存位置           // 下载方式
$RiSync->setInfo("mirror.tcotp.cn", "F:/Project/RiSync/", DOWNLOAD_CURL);
$RiSync->start();
