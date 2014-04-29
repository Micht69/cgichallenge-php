<?php

require_once 'Game.php';
require_once 'MyOrder.php';
define('PHP_INT_MIN', ~PHP_INT_MAX); 

/**
 * Bot
 * @author schmittse
 * 
 * TODO :
 *   - Analyser actions des autres
 *   - Rester 1 planete eco en avance
 *   - Définir zone de calme où les planètes peuvent être dégarnies
 *   
 */
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
error_log("===========================");
		$this->game = $g;
		$this->initOrUpdateData();
		$this->game = $g;
		//error_log("turn ".$this->game->turns);
		$this->initOrUpdateData();

		$planetsOrders = array();

		// (0) Send reinforcement from eco to military
		$myEcoPlanets = $this->game->myEconomicPlanets();
		foreach ($myEcoPlanets as $ecoPlanet) {
			if ($ecoPlanet->numShips > 50) {
				$target = $this->game->findNearestMilitaryPlanet($ecoPlanet);
				if ($target != null) {
					$planetsOrders[$ecoPlanet->id] = new MyOrder($ecoPlanet->id, $target->id, $ecoPlanet->numShips / 2);
					//$this->game->issueOrder($ecoPlanet->id, $target->id, $ecoPlanet->numShips / 2);
				}
			}
		}
		
		
		$myMilitaryPlanet = $this->game->myMilitaryPlanets();
		
		$ennemyFleets = $this->game->enemyFleets();
		if (count($ennemyFleets) > 0) {
			// Other bot moves ... react
		}
		
		// If not every planet issue an order, lets go
		for ($i=0; $i<count($myMilitaryPlanet); $i++) {
			$s = $myMilitaryPlanet[$i];
			/* @var $s Planet */
			if (!array_key_exists($s->id, $planetsOrders)) {
				// If planet as no order yet

				// Find weakest/closest neutral eco planet and take it
				$d = $this->getWeakestClosestPlanet($s, 2);
				if ($d == null) $d = $this->getWeakestPlanet();
				
				// Get ships number
				$n = $this->getNbrShipToTakePlanet($d);
				if ($n >= ($s->numShips - 20)) { // FIXME 20 Magic number (= incoming fleets ?)
					$n = $s->numShips / 2;
				}
				
				// Issue order
				$planetsOrders[$s->id] = new MyOrder($s->id, $d->id, $n);
			}
		}
		
		// Issue Orders
error_log("Nbr of orders = ".count($planetsOrders));
		foreach ($planetsOrders as $o) {
			/* @var $o MyOrder */
			$this->game->issueOrder($o->source_id, $o->dest_id, $o->numShips);
		}
	}

	/**
	 * Method called at the init phase of the Game
	 * (ie before first turn)
	 * !! No orders could be given here !!
	 * 
	 * @param Game $game
	 */
	public function doReadyTurn( $game )
	{
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
	 * @param int $type 1=notMyPlanets, 2=neutralPlanets, 3=enemyPlanets
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
	 * @param int $type 1=notMyPlanets, 2=neutralPlanets, 3=enemyPlanets
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
	
	/**
	 * Return number of ship to send to take the target planet
	 * 
	 * @param Planet $targetPlanet
	 * @return number
	 */
	private function getNbrShipToTakePlanet($targetPlanet) {
		$cnt = 0;
		if ($targetPlanet->owner != 1) {
			// Not my planet, need 1 more ship to take it
			$cnt = $targetPlanet->numShips + 1;
		}
		
		// Sum all incomming fleet
		foreach ($this->game->enemyFleets() as $f) {
			if ($f->destinationPlanet == $targetPlanet->id) {
				$cnt += $f->numShips;
			}
		}

		// Substract my fleet
		foreach ($this->game->myFleets() as $f) {
			if ($f->destinationPlanet == $targetPlanet->id) {
				$cnt -= $f->numShips;
			}
		}
		
		return $cnt;
	}
	
	/**
	 * Return the population of the planet in the defined number of turn
	 * 
	 * @param Planet $planet
	 * @param number $nbrTurn
	 * @return number
	 */
	private function getPlanetFuturePopulation($planet, $nbrTurn) {
		$cnt = $planet->numShips;

		// Sum all incomming fleet if arrived
		foreach ($this->game->enemyFleets() as $f) {
			if ($f->destinationPlanet == $targetPlanet->id && $f->turnsRemaining <= $nbrTurn) {
				$cnt += $f->numShips;
			}
		}

		// Substract my fleet if arrived
		foreach ($this->game->myFleets() as $f) {
			if ($f->destinationPlanet == $targetPlanet->id && $f->turnsRemaining <= $nbrTurn) {
				$cnt -= $f->numShips;
			}
		}
		
		return $cnt;
	}

	/**
	 * Choose which order to make
	 * 
	 *   - Algo de choix de planète :
	 *	  - eco si moins de 1 planete eco en avance
	 *		 - plus prêt mais pas trop peuplée
	 *		 - prendre en compte zone de confiance
	 *	  - militaire
	 *		 - neutre proche
	 *		 - ennemy si peu peuplée (prendre en compte ravitaillements ...)
	 *	  - snipe ? (prise en compte de tous les attaquants)
	 * 
	 * @return string
	 */
	private function chooseMainOrderType() {
		$planets = array_merge($this->game->enemyPlanets(), $this->game->myPlanets());
		
		// TODO Check for snipping available

		$eco_count = array_fill(0, $this->playerCount, 0);
		$mil_count = array_fill(0, $this->playerCount, 0);
		// Get max eco planets per player
		foreach ($planets as $p) {
			/* @var $p Planet */
			if ($p->type == PLANET_ECONOMIC) {
				$eco_count[$p->owner] += 1;
			} else if ($p->type == PLANET_MILITARY) {
				$mil_count[$p->owner] += 1;
			}
		}

		$eco_max = 0;
		$mil_max = 0;
		for ($i=2; $i<$this->playerCount; $i++) {
			if ($eco_count[$i] > $eco_max) {
				$eco_max = $eco_count[$i];
			}
			if ($mil_count[$i] > $mil_max) {
				$mil_max = $mil_count[$i];
			}
		}
		
		if ($eco_count[1] < ($mil_max + 1)) {
			return "ECO";
		}


		// TODO Check for military order
		return "MIL";
	}
}

/**
 * Don't run bot when unit-testing
 */
if( !defined('PHPUnit_MAIN_METHOD') ) {
	game::run( new MyBot() );
}