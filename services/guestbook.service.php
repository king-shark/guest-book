<?php

	/*
		A simple guestbook rest service in php. 

		There are two service calls here:

		GET /entries - returns guestbook entries
		POST /entries - posts a new guestbook entry		

		POST format is JSON, make sure your client sets the following header:
			
			Content-Type: application/json
		
		Example payload for POST:

			{ "name": "john", "email": "blah@blah.com", "text": "something funny" } 

		The script can be configured with a config json file. 

		By default, the config json file is "./guestbook.config.json".

		You can override this guestbook config location by specifying the following environment variable:

			export GUESTBOOK_CONFIG_LOCATION="./some/path/to/guestbook.config.json";

		If the config file doesn't exist, default configuration will not be overridden.

		The config file's contents look like this: 

			{
				"filename": "/path/to/entries.file.json",
				"enable_debug": false,
				"allow_duplicate_submissions": false
			}		

		Config flags: 

			filename - guestbook entries file location. 

			enable_debug - enable debug data dumped in the response json for service calls

			allow_duplicate_submissions - allow more than one submission from a user
	*/

	function check_json_error() {
		if (!json_last_error()) {
			return null;
		}
		throw new Exception("JSON Error: " + json_last_error_msg());
	}	

	function write_response($response) {
		header('Content-Type: application/json');
		echo pretty_format_json($response);
	}

	function pretty_format_json($obj) {
		$jsonPretty = json_encode($obj, JSON_PRETTY_PRINT);
		check_json_error();		
		return $jsonPretty;
	}	

	function get_guestbook_entries($filename) {
		if (!file_exists($filename)) {
			return null;
		}
		$guestbook_entries = file_get_contents($filename);		
		$guestbook_entries = json_decode($guestbook_entries, true);
		check_json_error();
		return $guestbook_entries;
	}		
			
	$response = array();
	
	try {
		$config_file = "./guestbook.config.json";

		if (isset($_ENV["GUESTBOOK_CONFIG_LOCATION"])) {
			$config_file = $_ENV["GUESTBOOK_CONFIG_LOCATION"];
		}

		$entries_file = "./guestbook.entries.json";
		$allow_duplicate_submissions = false;
		$enable_debug = false;

		if (file_exists($config_file)) {
			$config = file_get_contents($config_file);
			$config = json_decode($config, true);
			check_json_error();
			if (isset($config["filename"])) {
				$entries_file = $config["filename"];				
			}
			if (isset($config["allow_duplicate_submissions"])) {
				$allow_duplicate_submissions = $config["allow_duplicate_submissions"];				
			}
			if (isset($config["enable_debug"])) {
				$enable_debug = $config["enable_debug"];				
			}
		}		
	
		if ($enable_debug) {
			error_reporting(E_ALL);
			ini_set('display_errors', 1);
			$response["server_state"] = $_SERVER;
			$response["config_file"] = $config_file;
			$response["entries_file"] = $entries_file;
			$response["allow_duplicate_submissions"] = $allow_duplicate_submissions;
		}

		if ($_SERVER["PATH_INFO"] !== "/entries") {
			throw new Exception("Unsupported path: " . $_SERVER["PATH_INFO"]);
		} 
		$method = $_SERVER["REQUEST_METHOD"];
		if (($method !== "GET") && ($method !== "POST")) {
			throw new Exception("Unsupported request method: " . $method);
		}
		if ($method === "GET") {
			$response["entries"] = get_guestbook_entries($entries_file);	
		} else if ($_SERVER["REQUEST_METHOD"] === "POST") {
			/*
				example request: { "name": "greg", "email": "blah@blah.com", "text": "something funny" } 
			*/
			$post_json = file_get_contents('php://input');
			if ($enable_debug) {
				$response["post_data"] = $post_json;
			}			

			$post_data = json_decode($post_json, true);
			check_json_error();

			if ($post_data == null) {
				throw new Exception("Nothing was submitted.");
			} else if (empty($post_data["name"])) {
				throw new Exception("Name is required.");
			} else if (empty($post_data["email"])) {
				throw new Exception("Email is required.");
			} else if (empty($post_data["text"])) {
				throw new Exception("Text is required.");
			}
			$entries = get_guestbook_entries($entries_file);
			if ($entries == null) {
				$entries = array();
				$entries["entries"] = array();
			}
			if (!$allow_duplicate_submissions) {
				//this is pretty naive, it might be better to filter on ip address
				$name = $post_data["name"];
				$email = $post_data["email"];
				foreach ($entries["entries"] as $entry) {
					if ($entry["name"] === $name && $entry["email"] === $email) {
						throw new Exception("Entry for this user already exists.");
						break;
					}
				}
			}
			array_push($entries["entries"], $post_data);
			$entries_json = json_encode($entries, true);
			check_json_error();
			file_put_contents($entries_file, $entries_json);					
			$response["entry_saved"] = true;
		}
	} catch (Exception $e) {
		if ($enable_debug) {
			$details = array(); 
			$details["message"] = $e->getMessage();
			$details["code"] = $e->getCode();
			$details["file"] = $e->getFile();
			$details["line"] = $e->getLine();
			$details["trace"] = $e->getTraceAsString();
			$response["error_details"] = $details;
		}
		$response["error"] = $e->getMessage();
	}	
	write_response($response);
?>
