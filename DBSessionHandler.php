<?php
class DBSessionHandler implements SessionHandlerInterface
{
	private $db; //db connection
	private $useTransactions; //determines whether to use transactions
	private $expiration; //session expiration time
	private $unlockStatements = []; //array of statements to release application level locks
	private $collectGarbage = false; //set true by function gc() to tell function close() to run garbage collection

	public function __construct(PDO $db, $useTransactions)
	{
		$this->db = $db;
		$this->useTransactions = $useTransactions;
		$this->expiration = time() + (int) ini_get('session.gc_maxlifetime');

		session_set_save_handler($this);
		session_start();
	}

	//opens session
	public function open($save_path, $name)
	{
		return true;
	}
	//reads data from db
	public function read($session_id)
	{
		try
		{
			if($this->useTransactions)
			{
				$this->db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); //setting because the default isolation level (REPEATABLE READ) causes deadlock for different sessions
				$this->db->beginTransaction();
			}
			else $this->unlockStatements[] = $this->getLock($session_id);

			$sql = "SELECT expiration, data
					FROM sessions WHERE id = :id";
			//add FOR UPDATE to query when using transactions to avoid deadlock of connection that reads before write
			if($this->useTransactions)
			{
				$sql .= ' FOR UPDATE';
			}
			$stmt_readSelect = $this->db->prepare($sql);
			$stmt_readSelect->bindParam(':id', $session_id);
			$stmt_readSelect->execute();
			$result = $stmt_readSelect->fetch(PDO::FETCH_ASSOC);

			if($result) //check if session exists in the db
			{
				if($result['expiration'] < time()) //check if current time is later than session expiration time
				{
					return '';
				}
				return $result['data'];
			}
			//if the session has not yet been recorded in the db (query returns no results)
			if($this->useTransactions)
			{
				$this->initializeRecord($stmt_readSelect);
			}
			return '';
		}
		catch(PDOException $e)
		{
			if ($this->db->inTransaction())
			{
				$this->db->rollBack();
			}
			error_log($e->getMessage());
		}
	}
	//writes data to db
	public function write($session_id, $data)
	{
		try //Inserts if id does not yet exists, updates if it does
		{
			$sql = "INSERT INTO sessions (id, expiration, data)
					VALUES (:id, :expiration, :data)
					ON DUPLICATE KEY UPDATE
					expiration = :expiration, data = :data";
			$stmt_writeInsert = $this->db->prepare($sql);
			$stmt_writeInsert->bindParam(':id', $session_id);
			$stmt_writeInsert->bindParam(':expiration', $this->expiration, PDO::PARAM_INT);
			$stmt_writeInsert->bindParam(':data', $data);
			$stmt_writeInsert->execute();
			return true;
		}
		catch(PDOException $e)
		{
			if($this->db->inTransaction())
			{
				$this->db->rollBack();
			}
			error_log($e->getMessage());
		}
	}
	//closes session and writes data to db (also does the actual garbage collection process)
	public function close()
	{
		if($this->db->inTransaction())
		{
			$this->db->commit();
		}
		elseif($this->unlockStatements)
		{
			while($unlockStmt = array_shift($this->unlockStatements))
			{
				$unlockStmt->execute();
			}
		}

		//garbage collection
		if($this->collectGarbage) //$this->collectGarbage set false by default, set true by function gc() if garbage collection is needed
		{
			$sql = "DELETE FROM sessions
					WHERE expiration < :time";
			$stmt_closeDelete = $this->db->prepare($sql);
			$stmt_closeDelete->bindValue(':time', time(), PDO::PARAM_INT);
			$stmt_closeDelete->execute();
			$this->collectGarbage = false;
		}
		return true;
	}
	//destroys session
	public function destroy($session_id)
	{
		$sql = "DELETE FROM sessions
				WHERE id = :id";
		try
		{
			$stmt_destroyDelete = $this->db->prepare($sql);
			$stmt_destroyDelete->bindParam(':id', $session_id);
			$stmt_destroyDelete->execute();
		}
		catch(PDOException $e)
		{
			if($this->db->inTransaction())
			{
				$this->db->rollBack();
			}
			error_log($e->getMessage());
		}
		return true;
	}
	//garbage collection (tells function close() whether to collect garbage)
	public function gc($maxlifetime)
	{
		$this->collectGarbage = true;
		return true;
	}

	//in case of not using transactions in function read()
	private function getLock($session_id)
	{
		$stmt_getLockSelect = $this->db->prepare('SELECT GET_LOCK(:key, 50)');
		$stmt_getLockSelect->bindValue(':key', $session_id);
		$stmt_getLockSelect->execute();

		$releaseStmt = $this->db->prepare('DO RELEASE_LOCK(:key)');
		$releaseStmt->bindValue(':key', $session_id);

		return $releaseStmt;
	}
	//writes initial record of session to db
	private function initializeRecord(PDOStatement $stmt_readSelect)
	{
		try
		{
			$sql = "INSERT INTO sessions (id, expiration, data)
					VALUES (:id, :expiration, :data)";
			$stmt_initializeRecordInsert = $this->db->prepare($sql);
			$stmt_initializeRecordInsert->bindParam(':id', $session_id);
			$stmt_initializeRecordInsert->bindParam(':expiration', $this->expiration, PDO::PARAM_INT);
			$stmt_initializeRecordInsert->bindValue(':data', '');
			$stmt_initializeRecordInsert->execute();
			return '';
		}
		catch(PDOException $e)
		{
			if(0 === strpos($e->getCode(), '23'))
			{
				$stmt_readSelect->execute();
				$result = $stmt_readSelect->fetch(PDO::FETCH_ASSOC);
				if($result)
				{
					return $result['data'];
				}
				return '';
			}
			if($this->db->inTransaction())
			{
				$this->db->rollback();
			}
			error_log($e->getMessage());
		}
	}
}
?>
