<?php 
/**
 * 本类主要提供了一个依据每行的权重（也可以无权重随机读取）在文件中随机读取一行文本的功能
 * Theclass provides a basis for the right of each row (also non-weighted random read) in the file random reads a line of text features
 * 有人会问为什么都是静态方法
 * 因为我认为这个类不仅仅需要在WEB下面运行，这个类在CLIENT模式也是很有用的。
 * 
 * 如果是PHP的WEB模式，那么用户每次关闭浏览器，脚本直接退出，对象销毁，无所谓静态还是非静态。
 * 
 * 如果是CLIENT模式，那么每次调用都需要创建对象，会造成大量的浪费，所以，功能函数使用了静态。
 */
class PHPReadALineWithLineWeight {
	//文件中，每行 权重数字 与 正文文本之间的分割符，默认为空格，不过建议以TAB作为分割符号。
	public static $splitSymbol = " ";
	//文件操作状态，当本类被销毁时候，重写的destroy魔术方法会返回该变量的值。
	public static $fileStatusMessage = "";
	//运用IOC，避免重复创建Memcache服务器。
	public static $memcached;   
	/**
	 * 获取服务器的剩余空间大小，通过单位的不同显示出是否还有剩余空间以及剩余空间的大小
	 * @param  [type] $uploadDirPath   接受转储文件的文件夹路径
	 * @param  string $sizeFormat	  文件大小的后缀限制现在是有MB以及GB区别
	 * @param  string $diskSafetySpace 磁盘剩余空间的安全限额，确保服务器的正常使用
	 * @return [type]				  返回磁盘目前的剩余可分配空间（已经去除了安全配额的影响）
	 */
	function getServerDiskFreeSafeSpace($uploadDirPath, $sizeFormat = "MB", $diskSafetySpace = "5000MB") {
		$uploadDirPath = self::makePathSuitForWindows($uploadDirPath);
		$currentDiskFreeSpace = disk_free_space($uploadDirPath);
		if (strpos(strtoupper($diskSafetySpace) , 'MB')) {
			$diskSafetySpace = intval(str_replace("MB", "", $diskSafetySpace)) * 1024;
		} elseif (strpos(strtoupper($diskSafetySpace) , 'GB')) {
			$diskSafetySpace = intval(str_replace("MB", "", $diskSafetySpace)) *
			 1024 * 1024;
		} else {
			$diskSafetySpace = intval(str_replace("MB", "", $diskSafetySpace)) * 1024;
		}
		$diskSafetyFreeSpace = 0;
		switch ($sizeFormat) {
			case 'GB':
				$diskSafetyFreeSpace = intval($diskSafetyFreeSpace + (($currentDiskFreeSpace - $diskSafetySpace) / 1024) / 1024);
				break;

			default:
				$diskSafetyFreeSpace = intval($diskSafetyFreeSpace + ($currentDiskFreeSpace - $diskSafetySpace) / 1024);
				break;
		}
		if ($diskSafetyFreeSpace <= 0) {
			self::$fileStatusMessage = "Server has not enough space ."
		}
		return $diskSafetyFreeSpace;
	}
	/**
	 * 获取客户端的待上传文件的Hash值用来与服务器端比较
	 * 该模块暂停开发，没有想到合适的解决方案
	 * 但作为预留模块进行保留
	 * @return [type] [description]
	 */
	function getClientFileHashUseJS() {
		/**
		 * ignore this function , just through judge free size enough . Upload .
		 */
	}
	/**
	 *  获取一个文件的Hash值用来与客户端文件hash的比较
	 * @param  [type] $filePath 需要Hash运算的文件的路径
	 * @return [type]返回已经算好的文件的Hash值
	 */
	function getServerFileHashIn($filePath) {
		return md5(getFileContentWithFlagPoint($filePath));
	}
	/**
	 * 从客户端接收文件，如果转储成功，将提示信息变更为上传成功
	 * @return [type] [description]
	 */
	function receiveFileFromClient() {
		$submitFormFileFieldsName = 'upfile';
		$beStoragedFile = $_FILES[$submitFormFileFieldsName];
		$beStoragedFileName = $_FILES[$submitFormFileFieldsName]['name'];
		move_uploaded_file($file[$submitFormFileFieldsName]['tmp_name'], __FILE__ . DIRECTORY_SEPARATOR . "upload" . DIRECTORY_SEPARATOR . $beStoragedFileName);
		self::$fileStatusMessage = "File has been uploaded";
	}
	/**
	 * 返回禁止上传的信息，对提示信息做出变更
	 * @return [type] 没有返回值
	 */
	function forbiddenUploadFile() {
		self::$fileStatusMessage = "Forbidden Upload";
	}
	/**
	 * 获取一个文件夹下的所有子文件，包括文件夹、文件
	 * @param  [type] $dirPath 需要获取全部文件的文件夹路径
	 * @return [type]		  文件名称集合数组
	 */
	function getAllFileFromDir($dirPath) {
		/**
		 * 目的是竟可能的确保改路径合规
		 */
		if (isset($dirPath) && !empty($dirPath) && is_dir($dirPath) && is_readable($dirPath)) {
			$fileCollectionInSpecific = glob($dirPath);
			return $fileCollectionInSpecific;
		}
		return FALSE;
	}
	/**
	 * 对路径进行格式化，确保路径为UTF-8格式
	 * @param  [type] $filePath 需要转换为UTF8格式的字符串
	 * @return [type]		   返回一个已经编码为UTF-8编码的路径字符串
	 */
	public static function makePathSuitForWindows($filePath) {
		if (isset($filePath)) {
			$filePathCurrentEncoding = mb_check_encoding($filePath);
			$suitForCustomOsPath = mb_convert_encoding($filePath, "UTF-8", $filePathCurrentEncoding);
			return $suitForCustomOsPath;
		}
		return FALSE;
	}
	/**
	 * 传入一个路径，将其转换为UTF-8的字符串，散列后返回
	 * @param  [type] $filePath [description]
	 * @return [type]           [description]
	 */
	public static function getFilePathHashString($filePath) {
		@$filePath = self::makePathSuitForWindows($filePath);
		$salt = 'salt!@#';
		return hash("md5", $filePath . $salt);
	}
	/**
	 * 将散列好的字符串加另外一个字符串"[角标]"，得到 hashstring[0] 字样的字符串模拟数组
	 * @param  [type]  $filePath [description]
	 * @param  integer $fileLine [description]
	 * @return [type]            [description]
	 */
	public static function constructHashLine($filePath, $fileLine = 0) {
		return self::getFilePathHashString($filePath) . "[$fileLine]";
	}
	/**
	 * 判断Memcache中是否已有该文件，如果有的话返回 该文件对应行数的内容 hashstring[行数]
	 * @param  [type]  $filePath 文件路径
	 * @param  integer $fileLine 文件的行数
	 * @return [type]   如果不存在返回FALSE，如果存在返回值
	 */
	public static function judgeFileExistStateMemcache($filePath, $fileLine = 0) {
		if (!isset(self::$memcached)) {
			self::$memcached = new Memcached();
			self::$memcached->addServer(MEMCACHED_HOST, MEMCACHED_PORT);
		}
		$fileExistState = self::$memcached->get(self::constructHashLine($filePath, $fileLine));
		return $fileExistState;
	}
	/**
	 * 将内容从Memcache中取出，并json字符串解码为数组对象
	 * @param  [type]  $filePath          文件路径
	 * @param  integer $currentLineNumber 需要取值得行号
	 * @return [type]      返回对应行数的文件
	 */
	public static function getFileMemcachedLineAsArray($filePath, $currentLineNumber = 0) {
		return json_decode(self::judgeFileExistStateMemcache($filePath, $currentLineNumber));
	}
	/**
	 * 随机从文件中提取一行
	 * @param  [type]  $filePath 需要读取的原始文件路径
	 * @param  boolean $isWeight   需要读取的文件中是否含有Weight值用来标注某一行的出现频率高低
	 * @return [type]			返回文件中随机提取的某一行的内容
	 */
	public static function getRandLineContentFromFile($filePath, $isWeight = FALSE) {
		/**
		 * 判断该路径的文件是否已经存储在Memcache中，如果存在，那么返回其HASH[0]键值
		 * @var [type]
		 */
		$fileExistStateInMemcache = self::judgeFileExistStateMemcache($filePath);
		/**
		 * 如果存在
		 */
		if ($fileExistStateInMemcache) {
			/**
			 * 将HASH[0]键值解码，得到一个数组，取得对应文件的总行数，以及是否有权重，权重总和
			 * @var [type]
			 */
			$fileExistStateDescription = json_decode($fileExistStateInMemcache);
			$fileTotallyLineNumber = $fileExistStateDescription["line"];
			$fileExistWeightBollean = $fileExistStateDescription["weight"];
			$fileWeightSum = $fileExistStateDescription["sum"];
			/**
			 * 如果文件没有包含权重，那么直接随机一个行数，并取出
			 */
			if (FALSE == $fileExistWeightBollean) {
				$randLineNumber = rand(1, $fileTotallyLineNumber);
				$randLineContent = self::judgeFileExistStateMemcache($filePath, $randLineNumber);
				$randLineContent = $randLineContent["keyword"];
			} else {
			/**
			 * 如果文件含有权重，那么随机一个权重，通过分段比对，得到对应权重对应的行数的内容
			 * @var [type]
			 */
				$randWeight = rand(1, $fileWeightSum);
				$weightSumUntilFlag = 0;
				for ($currentLineNumber = 1; $currentLineNumber < $fileTotallyLineNumber; $currentLineNumber++) {
					$flagLineContentArray = getFileMemcachedLineAsArray($filePath, $currentLineNumber);
					$weightSumUntilFlag += $flagLineContentArray["weight"];
					if ($randWeight <= $weightSumUntilFlag) {
						return $flagLineContentArray["keyword"];
					}
				}
			}
			return $randLineContent;
		} else {
			/**
			 * 如果该文件没有在Memcache存储，那么把该文件读入到Memcache，递归本函数。
			 */
			if (self::setNewContentIntoMemcache($filePath)) {
				return self::getRandLineContentFromFile($filePath);
			} else {
				return FALSE;
			}
		}
	}


	/**
	 * 随机的获取某个文件的一行，不需要Weight值来设定出现的概率
	 * @param  [type] $filePath 原始文件的路径
	 * @return [type]		   返回随机获得的某个文件的一行
	 */
	function getRandLineContentFromWithOutRandFile($filePath) {
		return getALineContentFromFile($filePath, self::getRandomLineNumberFromFile($filePath));
	}
	/**
	 * 随机的获取某个文件的一行，文件中weight列设定为所在行出现的概率
	 * @param  [type] $filePath 原始文件的路径
	 * @return [type]		   返回随机获得的某个文件的一行
	 */
	function getRandLineContentFromRandFile($filePath) {
		$weightFilePath = $this->setNewContentWithWeight($filePath);
		$randomOriginalFileLineNumber = getALineContentFromFile($weightFilePath, self::getRandomLineNumberFromFile($weightFilePath));
		return getALineContentFromFile($filePath, $randomOriginalFileLineNumber);
	}
	/**
	 * 返回某个文件的全部内容
	 * @param  [type] $filePath			   原始文件路径
	 * @param  string $callFunctionDefineName 对每一行进行回调函数处理的函数名称
	 * @return [type]						 返回已经读取到的文件内容
	 */
	function getFileContentWithFlagPoint($filePath, $callFunctionDefineName = "pipeline") {
		@$filePath = self::makePathSuitForWindows($filePath);
		$fileContent = trim("");
		if (isset($filePath) && !empty($filePath) && is_readable($filePath)) {
			$fileHasBeenOpened = new SplFileObject($filePath, "r");
			foreach ($fileHasBeenOpened as $fileLineValue) {
				$fileLineValue = call_user_func_array($callFunctionDefineName, $fileLineValue);
				$fileContent.= $fileLineValue;
			}
		}
		return $fileContent;
	}
	/**
	 * 默认的回调函数，没有其他作用。
	 * @param  [type] $whatEver 任意对象
	 * @return [type]		   对象本身
	 */
	function pipeline($whatEver) {
		return $whatEver;
	}
	/**
	 * 构造一个文件属性数组
	 * 在遍历文件的同时将行数信息、是否有权重、权重总和放入属性数组
	 * 将文件的每一行keyword与weight分割，json encode后放入Memcache
	 * 
	 * @param [type] $filePath [description]
	 */
	function setNewContentIntoMemcache($filePath) {
		@$filePath = self::makePathSuitForWindows($filePath);
		$fileOrignalWeightArray = array(
			"line" => 0,
			"weight" => 0,
			"sum" => 0
		);
		if (isset($filePath) && !empty($filePath) && is_readable($filePath) && is_writeable(dirname($filePath))) {
			$fileHasBeenOpened = new SplFileObject($filePath, "r");
			foreach ($fileHasBeenOpened as $fileLineNo => $fileLineValue) {
				$fileLineSplitByWeight = self::independendWeightFromLineArray(self::getArraySplitedFileLineUseSpace($fileLineValue));
				if ($fileLineSplitByWeight["weight"]) {
					$fileOrignalWeightArray["weight"] = 1;
				}
				$fileOrignalWeightArray["sum"]+= $fileLineSplitByWeight["weight"];
				$fileOrignalWeightArray["line"] = $fileLineNo + 1;
				self::$memcached -> set(self::constructHashLine($filePath, $fileOrignalWeightArray["line"]) , json_encode($fileLineSplitByWeight));
			}
			self::$memcached->set(self::constructHashLine($filePath, 0) , json_encode($fileOrignalWeightArray));
			return TRUE;
		}
		return FALSE;
	} 
	/**
	 * 获得文件中指定一行的内容
	 * @param  [type]  $filePath   需要查找的文件的路径
	 * @param  integer $lineNumber 需要寻找的行数
	 * @return [type]			  返回指定行数的文件内容
	 */
	public static function getALineContentFromFile($filePath, $lineNumber = 0) {
		@$filePath = self::makePathSuitForWindows($filePath);
		if (isset($filePath) && !empty($filePath) && is_readable($filePath)) {
			$fileStreemObject = new SplFileObject($filePath, 'rb');
			if ($fileLineCountValue >= $lineNumber) {
				return $fileStreemObject->seek($lineNumber);
			} else {
				return FALSE;
			}
		}
	}
	/**
	 * 获取一个随机的行数，且该行数不会越界
	 * @param  [type] $filePath 需要获取随机行数的文件的路径
	 * @return [type]		   返回一个随机的行数
	 */
	public static function getRandomLineNumberFromFile($filePath) {
		return rand(1, self::getFileLineCount($filePath));
	}
	/**
	 * 获取一个文件的总共行数
	 * @param  [type] $filePath 需要获取总行数的文件的路径
	 * @return [type]		   返回一个文件的整数形式的行数
	 */
	public static function getFileLineCount($filePath) {
		@$filePath = self::makePathSuitForWindows($filePath);
		$fileLineCount = 0;
		if (isset($filePath) && !empty($filePath) && is_readable($filePath)) {
			$fileStreemObject = new SplFileObject($filePath, 'rb');
			$fileStreemObject->seek($fileStreemObject->getSize());
			$fileLineCount = intval($fileStreemObject->key());
		}
		return $fileLineCount;
	}
	/**
	 * 将已经分割好的行文本，提取出最后一个元素，且以空格作为分隔符合并除最后一个元素外的所有元素成为一个新的字符串
	 * 将新的字符串与最后一个元素独立的字符串组合为一个新的数组
	 * @param  [type] $fileLineArray 将行文本用空格分隔好的数组
	 * @return [type]				返回一个一维数组，其中包括了 原来数组的最后一个元素，以及除了最后一个元素以外的所有元素的集合字符串
	 */
	public static function independendWeightFromLineArray($fileLineArray, $weightFileSeparator = " ") {
		$fileProgressedArray = array(
			"weight" => NULL,
			"keyword" => NULL
		);
		if (isset($fileLineArray) && is_array($fileLineArray)) {
			$fileLineWeight = intval(end($fileLineArray));
			array_pop($fileLineArray);
			$fileRemovedWeight = implode($weightFileSeparator, $fileLineArray);
			$fileProgressedArray["weight"] = $fileLineWeight;
			$fileProgressedArray["keyword"] = $fileRemovedWeight;
			return $fileProgressedArray;
		}
		return FALSE;
	}
	/**
	 * 将行文本以空格为分隔符分开得到一个新的数组
	 * @param  [type] $fileLineValue 需要使用分隔符分割的原有行文本
	 * @return [type]				返回一个用分隔符分割好的数组
	 */
	function getArraySplitedFileLineUseSpace($fileLineValue) {
		if (isset($fileLineValue) && !empty($fileLineValue)) {
			$fileLineArray = explode(" ", $fileLineValue);
			return $fileLineValue;
		}
		return FALSE;
	}
	/**
	 *  从服务器删除一个文件
	 * @param  [type] $filePath 需要删除的文件的路径
	 * @return [type]		   返回删除是否成功，删除成功返回True，如果删除失败那么返回False，如果出现异常那么直接结束脚本
	 */
	function deleteFileFromServer($filePath) {·
		if (isset($filePath)) {
			if (file_exists($filePath)) {
				$fileWrapperFolderPath = dirname($filePath);
				if (is_writeable($fileWrapperFolderPath)) {
					unlink($filePath) or die("permission");
					return TRUE;
				}
			}
		}
		return FALSE;
	}
	/**
	 * 本文就主要是用来判断目标文件是否为GBK格式的中文文件
	 * 悲剧的是，没有想到什么好的方法转换格式以及检测格式，误差依然很大
	 * @param  [type] $originalFilePath 需要判断是否转码的文件的路径
	 * @return [type]				   返回需要转码并已经转码完毕的文件的路径，或者是不需要转码的文件的原始文件路径
	 */
	function autoExchangeEncode($originalFilePath) {
		if (!empty($originalFilePath)) {
			$isChinese = $this->judgeChinese($originalFilePath);
			if ($isChinese) {
				return $this->coverEncoding($originalFilePath);
			} else {
				return $originalFilePath;
			}
		} else {
			die("404 Not Found !" . __METHOD__);
		}
	}
	/**
	 * 判断该字节是否为汉语中的一部分，原理是当截取异常，汉字会表现为ASCII码中127位以后的字符，如果存在这些字符，那么对该文件转码
	 * @param  [type] $specificCharacter 需要检测的文件的字符
	 * @return [type]					返回是否为汉语，如果检测是汉语那么返回TRUE，如果不是汉语那么返回FALSE
	 */
	function judgeChinese($specificCharacter) {
		$specificCharacter = substr($specificCharacter, 0, 1);
		/**
		 * ord 查看当前字符的ASCII码
		 */
		if (ord($specificCharacter) > 127) {
			return TRUE;
		}
		return FALSE;
	}
	/**
	 * 对文件进行转码，并将转码后的文件的路径返回给函数调用者
	 * @param  [type] $originalFilePath 需要转码的文件的路径
	 * @return [type]				   返回已经另存为新编码格式的文件的路径
	 */
	function coverEncoding($originalFilePath) {
		return $originalFilePath;
	}
	/**
	 * 在该对象销毁时候输出需要提示的内容，确保无论如何，客户端均能够接收到返回信息。
	 * @return [type] [description]
	 */
	function __destroy() {
		echo json_encode(self::$fileStatusMessage);
	}
}

