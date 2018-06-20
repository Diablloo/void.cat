<?php
	include_once('config.php');
	include_once('file.php');
	
	class DB {
		function __construct()
		{
			$this->error = null;
			$this->mysqli = new mysqli(_DB_HOST, _DB_USER, _DB_PASS, _DB_DATABASE);
			
			if ($this->mysqli->connect_errno) {
				$this->error = "Failed to connect to MySQL: (" . $this->mysqli->connect_errno . ") " . $this->mysqli->connect_error;
			}
		}
		
		function __destruct()
		{
			if($this->error == null)
			{
				$this->mysqli->close(); 
			}
		}
		
		function Exists256($hash)
		{
			return $this->GetFile($hash, "hash256");
		}
		
		function GetStats()
		{
			$res = new FileStats();
			
			$stmt = $this->mysqli->prepare("select count(hash160), sum(size), avg(size), sum(views * size) from files");
			if($stmt)
			{
				$stmt->execute();
				$stmt->bind_result($res->files, $res->size, $res->avgSize, $res->transfer);
				$stmt->fetch();
				$stmt->close();

				$res->size = floatval($res->size);
				$res->avgSize = floatval($res->avgSize);
				$res->transfer = floatval($res->transfer);
			}
			
			return $res;
		}
		
		function GuessHash($hash) {
			//sometimes are making a request like this: lets just do a LIKE query to find it bf9fdaa217271f9e1f6ab88...f42bd0a538
			//poor lost people i cri evry time
			$res = null;
			
			$stmt = $this->mysqli->prepare("select hash160 from files where hash160 like ? limit 1");
			if($stmt)
			{
				$stmt->bind_param("s", $hash);
				$stmt->execute();
				$stmt->bind_result($res);
				$stmt->fetch();
				$stmt->close();
			}
			
			return $res;
		}
		
		function GetFile($hash, $hc = "hash160")
		{
			$res = new FileUpload();
			
			$stmt = $this->mysqli->prepare("select hash160, hash256, filename, mime, size, path, views, isAdminFile, uploaded, lastview from files where " . $hc . " = ? limit 1");
			if($stmt)
			{
				$stmt->bind_param("s", $hash);
				$stmt->execute();
				$stmt->bind_result($res->hash160, $res->hash256, $res->filename, $res->mime, $res->size, $res->path, $res->views, $res->isAdminFile, $res->uploaded, $res->lastview);
				$stmt->fetch();
				$stmt->close();
			}
			
			return $res;
		}

		function GetFiles()
		{
			$res = array();

			$stmt = $this->mysqli->prepare("select hash160, hash256, filename, mime, size, path, views, isAdminFile, uploaded, lastview from files order by uploaded desc");
			if($stmt)
			{
				$stmt->execute();
				$stmt->bind_result($hash160, $hash256, $filename, $mime, $size, $path, $views, $isAdminFile, $uploaded, $lastview);
				while($stmt->fetch()){
					$nf = new FileUpload();
					$nf->hash160 = $hash160;
					$nf->hash256 = $hash256;
					$nf->filename = $filename;
					$nf->mime = $mime;
					$nf->size = $size;
					$nf->path = $path;
					$nf->views = $views;
					$nf->isAdminFile = $isAdminFile;
					$nf->uploaded = $uploaded;
					$nf->lastview = $lastview;
					
					array_push($res, $nf);
				}
				$stmt->close();
			}

			return $res;
		}
		
		function InsertFile($f)
		{
			$stmt = $this->mysqli->prepare("insert into files(hash160, hash256, filename, mime, size, path) values(?,?,?,?,?,?)");
			if($stmt)
			{
				$stmt->bind_param("ssssss", $f->hash160, $f->hash256, $f->filename, $f->mime, $f->size, $f->path);
				$stmt->execute();
				$stmt->close();
			}
		}

		function DeleteFile($f)
		{
			$stmt = $this->mysqli->prepare("delete from files where hash160 = ?");
			if($stmt)
			{
				$stmt->bind_param("s", $f->hash160);
				$stmt->execute();
				$stmt->close();
			}
		}

		function UpdateFileSize($h, $s)
		{
			$stmt = $this->mysqli->prepare("update files set size = ? where hash160 = ?");
			if($stmt)
			{
				$stmt->bind_param("ds", $s, $h);
				$stmt->execute();
				$stmt->close();
			}
		}
		
		function AddView($hash160)
		{
			$stmt = $this->mysqli->prepare("update files set views = views + 1, lastview = NOW() where hash160 = ?");
			if($stmt)
			{
				$stmt->bind_param("s", $hash160);
				$stmt->execute();
				$stmt->close();
			}
		}

		function GetExpiredFiles()
		{
			return $this->GetExpiredFilesT(_FILE_EXPIRE_TIME);
		}
		
		function GetExpiredFilesT($tt)
		{
			$res = array();

			$stmt = $this->mysqli->prepare("select hash160, filename, path, lastview, size from files where date_add(lastview, INTERVAL " . $tt . " DAY) < CURRENT_TIMESTAMP or (views = 0 and date_add(lastview, INTERVAL 1 DAY) < CURRENT_TIMESTAMP) order by lastview desc");
			if($stmt)
			{
				$stmt->execute();
				$stmt->bind_result($hash160, $filename, $path, $lastview, $size);
				while($stmt->fetch()){
					$nf = new FileUpload();
					$nf->hash160 = $hash160;
					$nf->filename = $filename;
					$nf->path = $path;
					$nf->lastview = $lastview;
					$nf->size = $size;
					array_push($res, $nf);
				}
				$stmt->close();
			}

			return $res;
		}
	};
?>