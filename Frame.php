<?php

include('xstruct.php');

class Frame {
	public static $header_size = 16; /* How long the header is */
	private static $header_struct = "4sIII"; /* The structure of the header */
	private static $header_array = array("protocol", "sequence", "type", "length"); /* What the values map too */

	const FAIL = 1;
	const SEQUENCE = 2;
	const CONNECT = 3;
	const LOGIN = 4;

	private static $data_structs = array(
		Frame::FAIL => "IS",
		Frame::SEQUENCE => "I",
		Frame::CONNECT => "S",
		Frame::LOGIN => "SS",
	);
	
	private static $data_arrays = array(
		Frame::FAIL => array('type', 'desc'),
		Frame::SEQUENCE => array('no'),
		Frame::CONNECT => array('s'),
		Frame::LOGIN => array('username', 'password'),
	);

	private $v = array();

	public function __get($nm) {
		if (isset($this->v[$nm])) {
			return $this->v[$nm];
		}
	}

	public function __set($nm, $val) {
		if ( in_array($nm, Frame::$header_array) or in_array($nm, Frame::$data_arrays[$this->type]) ){
			$this->x[$nm] = $val;
		} else
			throw new Exception("No such attribute can exist on this object.");
	}

	public function __isset($nm) {
		return isset($this->v[$nm]);
	}

	public function __unset($nm){
		unset($this->v[$nm]);
	}

	function parse_header($string) {
		if (strlen($string) != Frame::$header_size)
			throw new Exception("Header string size was not correct! Length was " . 
				strlen($string) . " required " . Frame::$header_size);

		list($temp, $string) = unpack_full(Frame::$header_struct, $string);
		$this->v = array_combine(Frame::$header_array, $temp);
	}

	function parse_data($string) {
		if (strlen($string) != $this->length)
			throw new Exception("Data string size was not correct! Length was " . 
				strlen($string) . " required " . $this->length);

		list($temp, $string) = unpack_full(Frame::$data_structs[$this->type], $string);
		$temp = array_combine(Frame::$data_arrays[$this->type], $temp);

		$this->v = array_merge($this->v, $temp);
	}
}


$data = "TP03\x00\x00\x00\x01\x00\x00\x00\x03\x00\x00\x00\n\x00\x00\x00\x06peanut\0\0\0";

$f = new Frame();
print 'bh ' . bin2hex(substr($data, 0, Frame::$header_size)) . "\n";
$f->parse_header(substr($data, 0, Frame::$header_size));
print 'ah ' . bin2hex(substr($data, Frame::$header_size)) . "\n";
$f->parse_data(substr($data, Frame::$header_size, $f->length));
print 'ad ' . bin2hex($data) . "\n";

print_r($f);