<?php

require_once 'Game.php';
define('PHP_INT_MIN', ~PHP_INT_MAX); 

class MyBot
{
	/**
	 * @var Game
	 */
	private $game;
	/**
	 * @var Boolean
	 */
	private $isInitialized = FALSE;
	/**
	 * @var int
	 */
	private $playerCount = 0;
	/**
	 * @var int
	 */
	private $longest_distance = 0;
	
	/**
	 * @param Game $game
	 */
    public function doTurn( $g )
    {
    	$this->game = $g;
    	error_log("start ".$this->game->turns);
    	$this->initOrUpdateData();
    	
    	$planetsOrders = array();
    	$myMilitaryPlanet = $this->game->myMilitaryPlanets();
    	
    	$ennemyFleets = $this->game->enemyFleets();
    	if (count($ennemyFleets) > 0) {
    		// Other bot moves ... react
    	}
    	
    	// If not every planet issue an order, lets go
    	if (count($planetsOrders) < count($myMilitaryPlanet)) {
    		for ($i=0; $i<count($myMilitaryPlanet); $i++) {
    			if (array_key_exists($i, $planetsOrders)) {
    				// If planet as no order yet
    				$s = $myMilitaryPlanet[$i];
	    			/* @var $s Planet */

    				// Find weakest/closest neutral eco planet and take it
    				$d = $this->getWeakestClosestPlanet($s, 3);
    				if ($d == null) $d = $this->getWeakestPlanet();
    				
    				// Get ships number
    				if ($s->numShips > ($d->numShips + 20)) { // FIXME 20 Magic number (= incoming fleets ?)
						$n = ($d->numShips + 2);
	    				
	    				// Issue order
	    				$planetsOrders[$i] = new MyOrder($s->id, $d->id, $n);
    				}
    			}
    		}
    	}
    	
    	// Issue Orders
    	for ($i=0; $i<count($planetsOrders); $i++) {
    		$o = $planetsOrders[$i];
	    	/* @var $o MyOrder */
    		
    		$this->game->issueOrder($o->source_id, $o->dest_id, $o->numShips);
    	}
    	
		// (1) If we currently have a fleet in flight, just do nothing.
		if (count($this->game->myMilitaryFleets()) >= 1) {
			return;
		}
		
		// (2) Find my strongest military planet.
		$source = null;
		$sourceShips = PHP_INT_MIN;
		$planets = $this->game->myMilitaryPlanets();
		foreach ($planets as $p) {
			$score = $p->numShips;
			if ($score > $sourceShips) {
				$sourceShips = $score;
				$source = $p;
			}
		}

		// (3) Find the weakest enemy or neutral planet.
		$dest = null;
		$destScore = PHP_INT_MAX;
		$planets = $this->game->notMyPlanets();
		foreach ($planets as $p) {
			$score = $p->numShips;
			if ($score < $destScore) {
				$destScore = $score;
				$dest = $p;
			}
		}

		// (4) Send half the ships from my strongest planet to the weakest
		// planet that I do not own.
		if ($source != null && $dest != null) {
			$numShips = (int) ($source->numShips / 2);
			$this->game->issueOrder($source->id, $dest->id, $numShips);
		}
    }
    
    /**
     * Prepare data
     *   - get player counts
     *   - sort players ?
     *   ...
     */
    private function initOrUpdateData() {
    	if ($this->isInitialized) {
    		// Update data
    	} else {
    		// Init data
    		// Get Bot count ...
	    	foreach ($this->game->enemyPlanets() as $p) {
	    		if ($p->owner > $this->playerCount) {
	    			$this->playerCount = $p->owner;
	    		}
	    	}
	    	$this->playerCount = $this->playerCount - 1;
	    	
	    	// Get longest distance
	    	$allPlanets = array_merge($this->game->enemyPlanets, $this->game->neutralPlanets, $this->game->myPlanets);
	    	foreach ($allPlanets as $pSrc) {
	    		/* @var $pSrc Planet */
	    		foreach ($allPlanets as $pDest) {
	    			/* @var $pDest Planet */
	    			if ($pSrc->id != $pDest->id) {
	    				$d = $this->game->distanceWithPlanets($pSrc, $pDest);
	    				if ($d > $this->longest_distance) {
	    					$this->longest_distance = $d;
	    				}
	 				}
	    		}
	    	}

	    	$this->isInitialized = TRUE;
    	}
    }
    
    /**
     * Get weakest planet<br/>
     * 
     * @param int $type 1=notMyPlanets, 2=enemyPlanets, 3=neutralPlanets
     * @return Planet
     */
    private function getWeakestPlanet($type = 1) {
		$dest = null;
		$destScore = PHP_INT_MAX;
		if ($type == 1) {
			$planets = $this->game->notMyPlanets();
    	} else if ($type == 3) {
			$planets = $this->game->enemyPlanets;
    	} else {
			$planets = $this->game->neutralPlanets;
    	}
    	
		foreach ($planets as $p) {
			$score = $p->numShips;
			if ($score < $destScore) {
				$destScore = $score;
				$dest = $p;
			}
		}
		
		return $dest;
    }
    
    /**
     * Get weakest planet<br/>
     * Only if planet is half of max planet distance
     * 
     * @param Planet $center
     * @param int $type 1=notMyPlanets, 2=enemyPlanets, 3=neutralPlanets
     * @return Planet
     */
    private function getWeakestClosestPlanet($center, $type = 1) {
		$dest = null;
		$destScore = PHP_INT_MAX;
		if ($type == 1) {
			$planets = $this->game->notMyPlanets();
    	} else if ($type == 3) {
			$planets = $this->game->enemyPlanets;
    	} else {
			$planets = $this->game->neutralPlanets;
    	}
    
    	$halfDist = $this->longest_distance/2;
		foreach ($planets as $p) {
			$score = $p->numShips;
			if ($score < $destScore && $this->game->distanceWithPlanets($center, $p) < $halfDist) {
				$destScore = $score;
				$dest = $p;
			}
		}
		
		return $dest;
    }
    
    private function getClosestPlanet($center, $withNeutral = true) {
		$dest = null;
		$destScore = PHP_INT_MIN;
		if ($withNeutral) {
			$planets = $this->game->notMyPlanets();
    	} else {
			$planets = $this->game->enemyPlanets();
    	}
		foreach ($planets as $p) {
			$score = $p->numShips;
			if ($score > $destScore) {
				$destScore = $score;
				$dest = $p;
			}
		}
		
		return $dest;
    }
}

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

/**
 * Don't run bot when unit-testing
 */
if( !defined('PHPUnit_MAIN_METHOD') ) {
    game::run( new MyBot() );
}