<?php
class MyOrder
{
	/**
	 * @var int
	 */
	public $source_id;
	/**
	 * @var int
	 */
	public $dest_id;
	/**
	 * @var int
	 */
	public $numShips;

	/**
	 * @param int $s
	 * @param int $d
	 * @param int $n
	 */
	function __construct($s, $d, $n) {
		$this->source_id = $s;
		$this->dest_id = $d;
		$this->numShips = $n;
	}
}
