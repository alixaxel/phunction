<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_DB_SQL extends phunction_DB
{
	public $sql = array();

	public function __construct($sql = null)
	{
		if (isset($sql) === true)
		{
			$this->sql['sql'] = trim($sql);
		}
	}

	public function __toString()
	{
		$result = null;

		if (array_key_exists('query', $this->sql) === true)
		{
			$result .= $this->sql['query'];

			if (preg_match('~^(?:SELECT|UPDATE|DELETE)\b~', $result) > 0)
			{
				if ((strncmp('SELECT', $result, 6) === 0) && (array_key_exists('join', $this->sql) === true))
				{
					$result .= "\n\t" . implode("\n\t", $this->sql['join']);
				}

				if (array_key_exists('where', $this->sql) === true)
				{
					$result .= "\n\t" . implode("\n\t", $this->sql['where']);
				}

				if ((strncmp('SELECT', $result, 6) === 0) && (array_key_exists('group', $this->sql) === true))
				{
					$result .= "\n" . implode(', ', $this->sql['group']);

					if (array_key_exists('having', $this->sql) === true)
					{
						$result .= "\n\t" . implode("\n\t", $this->sql['having']);
					}
				}

				if (array_key_exists('order', $this->sql) === true)
				{
					$result .= "\n" . implode(', ', $this->sql['order']);
				}

				if (array_key_exists('limit', $this->sql) === true)
				{
					$result .= "\n" . $this->sql['limit'];
				}
			}

			if (array_key_exists('explain', $this->sql) === true)
			{
				$result = $this->sql['explain'] . "\n" . $result;
			}

			$result .= ';';
		}

		else if (array_key_exists('sql', $this->sql) === true)
		{
			$result .= sprintf('%s;', rtrim($this->sql['sql'], ';'));
		}

		return strval($result);
	}

	public function Delete($table)
	{
		$this->sql = array();

		if (is_object(phunction::DB()) === true)
		{
			if (is_array($table) !== true)
			{
				$table = array_filter(array_map('trim', explode(',', $table)), 'strlen');
			}

			if (count($table = parent::Tick($table)) == 1)
			{
				$this->sql['query'] = sprintf('DELETE FROM %s', implode(', ', $table));
			}
		}

		return $this;
	}

	public function Explain()
	{
		if (array_key_exists('query', $this->sql) === true)
		{
			$this->sql['explain'] = 'EXPLAIN';
		}

		return $this;
	}

	public function Group($data, $order = 'ASC')
	{
		if (array_key_exists('query', $this->sql) === true)
		{
			if (is_array($data) !== true)
			{
				$data = array_filter(array_map('trim', explode(',', $data)), 'strlen');
			}

			if (count($data = parent::Tick($data)) > 0)
			{
				foreach ($data as $key => $value)
				{
					if (empty($this->sql['group']) === true)
					{
						$value = sprintf('GROUP BY %s', $value);
					}

					$this->sql['group'][] = sprintf('%s %s', $value, $order);
				}
			}
		}

		return $this;
	}

	public function Having($data, $operator = 'LIKE', $merge = 'AND')
	{
		if ((array_key_exists('query', $this->sql) === true) && (count($data) > 0))
		{
			$merge = array($merge, 'HAVING');

			foreach ($data as $key => $value)
			{
				$key = parent::Tick($key);

				if (is_object($value) === true)
				{
					$value = sprintf('(%s)', rtrim(strval($value), ';'));
				}

				else if (is_array($value = array_map(array(phunction::DB(), 'quote'), (array) $value)) === true)
				{
					if (preg_match('~\b(?:NOT\s+)?BETWEEN\b~i', $operator) > 0)
					{
						$value = sprintf('%s AND %s', array_shift($value), array_shift($value));
					}

					$value = (preg_match('~\b(?:NOT\s+)?IN\b~i', $operator) > 0) ? sprintf('(%s)', implode(', ', $value)) : array_shift($value);
				}

				$this->sql['having'][] = sprintf('%s %s %s %s', $merge[empty($this->sql['where'])], $key, $operator, $value);
			}
		}

		return $this;
	}

	public function Insert($table, $data, $replace = false)
	{
		$this->sql = array();

		if (is_object(phunction::DB()) === true)
		{
			if (is_array($table) !== true)
			{
				$table = array_filter(array_map('trim', explode(',', $table)), 'strlen');
			}

			if ((count($table = parent::Tick($table)) == 1) && (count($data = array_map(array(phunction::DB(), 'quote'), $data)) > 0))
			{
				$this->sql['query'] = sprintf('%s INTO %s', ($replace === true) ? 'REPLACE' : 'INSERT', implode(', ', $table));

				if ($data !== array_values($data))
				{
					$this->sql['query'] .= sprintf(' (%s)', implode(', ', parent::Tick(array_keys($data))));
				}

				$this->sql['query'] .= sprintf(' VALUES (%s)', implode(', ', $data));
			}
		}

		return $this;
	}

	public function Join($table, $data = null, $type = null, $operator = '=', $merge = 'AND')
	{
		if (array_key_exists('query', $this->sql) === true)
		{
			foreach (array('data', 'table') as $value)
			{
				if (is_array($$value) !== true)
				{
					$$value = array_filter(array_map('trim', explode(',', $$value)), 'strlen');
				}
			}

			if (count($table = parent::Tick($table)) >= 1)
			{
				if (empty($data) === true)
				{
					$this->sql['join'][] = sprintf('%s JOIN %s', trim('NATURAL ' . $type), implode(', ', $table));
				}

				else if (count($data = parent::Tick($data)) > 0)
				{
					if ($data === array_values($data))
					{
						$this->sql['join'][] = sprintf('%s %s USING (%s)', trim($type . ' JOIN'), implode(', ', $table), implode(', ', $data));
					}

					else if (strlen($merge = sprintf(' %s ', trim($merge))) > 2)
					{
						foreach ($data as $key => $value)
						{
							$data[$key] = sprintf('%s %s %s', parent::Tick($key), $operator, $value);
						}

						$this->sql['join'][] = sprintf('%s %s ON (%s)', trim($type . ' JOIN'), implode(', ', $table), implode($merge, $data));
					}
				}
			}
		}

		return $this;
	}

	public function Limit($limit, $offset = null)
	{
		if (array_key_exists('query', $this->sql) === true)
		{
			$this->sql['limit'] = sprintf('LIMIT %u', $limit);

			if (isset($offset) === true)
			{
				$this->sql['limit'] .= sprintf(' OFFSET %u', $offset);
			}
		}

		return $this;
	}

	public function Order($data, $order = 'ASC')
	{
		if (array_key_exists('query', $this->sql) === true)
		{
			if (is_array($data) !== true)
			{
				$data = array_filter(array_map('trim', explode(',', $data)), 'strlen');
			}

			if (count($data = parent::Tick($data)) > 0)
			{
				foreach ($data as $key => $value)
				{
					if (empty($this->sql['order']) === true)
					{
						$value = sprintf('ORDER BY %s', $value);
					}

					$this->sql['order'][] = sprintf('%s %s', $value, $order);
				}
			}
		}

		return $this;
	}

	public function Select($table, $data = '*', $distinct = false)
	{
		$this->sql = array();

		if (is_object(phunction::DB()) === true)
		{
			foreach (array('data', 'table') as $value)
			{
				if (is_array($$value) !== true)
				{
					$$value = array_filter(array_map('trim', explode(',', $$value)), 'strlen');
				}
			}

			if (count($data = parent::Tick($data)) > 0)
			{
				$this->sql['query'] = sprintf('SELECT %s', implode(', ', $data));

				if (count($table = parent::Tick($table)) >= 1)
				{
					$this->sql['query'] .= sprintf(' FROM %s', implode(', ', $table));
				}

				$this->sql['query'] = preg_replace('~(SELECT)~', '$1' . (($distinct === true) ? ' DISTINCT' : ''), $this->sql['query'], 1);
			}
		}

		return $this;
	}

	public function Update($table, $data)
	{
		$this->sql = array();

		if (is_object(phunction::DB()) === true)
		{
			if (is_array($table) !== true)
			{
				$table = array_filter(array_map('trim', explode(',', $table)), 'strlen');
			}

			if ((count($table = parent::Tick($table)) >= 1) && (count($data = array_map(array(phunction::DB(), 'quote'), $data)) > 0))
			{
				foreach ($data as $key => $value)
				{
					$data[$key] = sprintf('%s = %s', parent::Tick($key), $value);
				}

				$this->sql['query'] = sprintf('UPDATE %s SET %s', implode(', ', $table), implode(', ', $data));
			}
		}

		return $this;
	}

	public function Where($data, $operator = 'LIKE', $merge = 'AND')
	{
		if ((array_key_exists('query', $this->sql) === true) && (count($data) > 0))
		{
			$merge = array($merge, 'WHERE');

			foreach ($data as $key => $value)
			{
				$key = parent::Tick($key);

				if (is_object($value) === true)
				{
					$value = sprintf('(%s)', rtrim(strval($value), ';'));
				}

				else if (is_array($value = array_map(array(phunction::DB(), 'quote'), (array) $value)) === true)
				{
					if (preg_match('~\b(?:NOT\s+)?BETWEEN\b~i', $operator) > 0)
					{
						$value = sprintf('%s AND %s', array_shift($value), array_shift($value));
					}

					$value = (preg_match('~\b(?:NOT\s+)?IN\b~i', $operator) > 0) ? sprintf('(%s)', implode(', ', $value)) : array_shift($value);
				}

				$this->sql['where'][] = sprintf('%s %s %s %s', $merge[empty($this->sql['where'])], $key, $operator, $value);
			}
		}

		return $this;
	}
}

?>