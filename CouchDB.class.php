<?php

class CouchDBModel {
	public function __construct () {
		$this->urlprefix = 'localhost:5984';
	}

	public function allDbs () {
		return $this->curl('/_all_dbs');
	}

	/**
	 * @param string $dbname
	 */
	public function addDb ($dbname) {
		return $this->curl('/'.$dbname, 'put');
	}

	/**
	 * @param string $dbname
	 */
	public function deleteDb ($dbname) {
		return $this->curl('/'.$dbname, 'delete');
	}

	/**
	 * @param string $dbname
	 * @param string $id
	 */
	public function getDocument ($dbname, $id) {
		return $this->curl('/'.$dbname.'/'.$id);
	}

	/**
	 * @param string $dbname
	 * @param json $postdata
	 */
	public function addDocument ($dbname, $postdata) {
		$url = '/'.$dbname.'/'.$this->getUUID();
		$postdata = json_decode($postdata);

		return $this->curl($url, 'put', $postdata);
	}

	/**
	 * @param string $dbname
	 * @param json array $postdata
	 */
	public function addMultipleDocuments ($dbname, $postdata) {
		$postdata = json_decode($postdata);

		foreach($postdata as $doc){
			set_time_limit(10);
			$url = '/'.$dbname.'/'.$this->getUUID();

			$resp[] = json_decode($this->curl($url, 'put', $doc));
		}

		return json_encode($resp);
	}

	/**
	 * @param string $dbname
	 * @param string $id
	 * @param json $postdata
	 * @param string $rev
	 */
	public function updateDocument ($dbname, $id, $postdata, $rev) {
		$url = '/'.$dbname.'/'.$id;
		$postdata = json_decode($postdata);
		$postdata->_rev = $rev;

		return $this->curl($url, 'put', $postdata);
	}

	/**
	 * @param string $dbname
	 * @param string $designname
	 * @param string $viewname
	 * @param string $viewfn
	 */
	public function addView ($dbname, $designname, $viewname, $viewfn) {
		$url = '/'.$dbname.'/_design/'.$designname;
		$viewdata = '{
			"_id": "_design/'.$designname.'",
			"views": {
				"'.$viewname.'": {
					"map": "'.$viewfn.'"
				}
			}
		}';

		return $this->curl($url, 'put', json_decode($viewdata));
	}

	/**
	 * @param string $dbname
	 * @param string $designname
	 * @param json array $viewname
	 * @param json array $viewfn
	 */
	public function addMultipleViews ($dbname, $designname, $viewname, $viewfn) {
		$url = '/'.$dbname.'/_design/'.$designname;
		$viewdata = '{
		    "_id": "_design/'.$designname.'",
			"views": {';

		$views = json_decode($viewname);
		$functions = json_decode($viewfn);

		if(count($views->views) === count($functions->functions)){
			for($i=0;$i<count($views->views);$i++){
				$viewdata .= '"'.$views->views[$i].'": {
					"map": "'.$functions->functions[$i].'"
					},';
			}
			$viewdata = substr($viewdata, 0, -1);
		} else {
			return json_encode(array('error' => 'num views not equal to num functions'));
		}

		$viewdata .= '}}';

		return $this->curl($url, 'put', json_decode($viewdata));
	}

	/**
	 * @param string $dbname
	 * @param string $designname
	 * @param string $rev
	 * @param string $viewname
	 * @param string $viewfn
	 */
	public function updateView ($dbname, $designname, $rev, $viewname, $viewfn) {
		$url = '/'.$dbname.'/_design/'.$designname;
		$viewdata = '{
			"_id": "_design/'.$designname.'",
			"_rev": "'.$rev.'",
			"views": {
				"'.$viewname.'": {
					"map": "'.$viewfn.'"
				}
			}
		}';

		return $this->curl($url, 'put', json_decode($viewdata));
	}

	/**
	 * @param string $dbname
	 */
	public function listDesigns ($dbname) {
		$url = '/'.$dbname.'/_all_docs?startkey="_design/"&endkey="_design0"';

		return $this->curl($url, 'get');
	}

	/**
	 * @param string $dbname
	 * @param string $designname
	 */
	public function getViews ($dbname, $designname) {
		$url = '/'.$dbname.'/_design/'.$designname;

		return $this->curl($url, 'get');
	}

	/**
	 * @param string $dbname
	 * @param string $designname
	 * @param string $viewname
	 * @param string optional $argument
	 */
	public function queryView ($dbname, $designname, $viewname, $argument = null) {
		$url = '/'.$dbname.'/_design/'.$designname.'/_view/'.$viewname;

		if(!is_null($argument)){
			$url .= '?key="'.urlencode($argument).'"';
		}

		return $this->curl($url, 'get');
	}

	private function getUUID () {
		$uuids = json_decode($this->curl('/_uuids'));

		return $uuids->uuids[0];
	}

	private function curl ($cmd, $method = 'get', $postdata = null) {
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, $this->urlprefix . $cmd);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: application/json"));

		if($method === 'post'){
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_decode($postdata));
		} elseif($method === 'put'){
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postdata));
		} elseif($method === 'delete'){
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
		} else {
			curl_setopt($curl, CURLOPT_POST, false);
		}

		$resp = curl_exec($curl);

		curl_close($curl);

		return $resp;
	}
}