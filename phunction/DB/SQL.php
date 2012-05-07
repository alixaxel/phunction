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

	public function __construct()
	{
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

		return (array_key_exists('literal', $this->sql) === true) ? $this->sql['literal'] : strval($result);
	}

	public function Delete($table)
	{
		$this->sql = array();

		if (is_object(phunction::DB()) === true)
		{
			$table = (is_array($table) !== true) ? explode(',', $table) : $table;

			if (count($table = parent::Tick(array_filter(array_map('trim', $table), 'strlen'))) == 1)
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
			$data = (is_array($data) !== true) ? explode(',', $data) : $data;

			if (count($data = parent::Tick(array_filter(array_map('trim', $data), 'strlen'))) > 0)
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

	public function Having($data, $operator = '=', $merge = 'AND')
	{
		if ((array_key_exists('query', $this->sql) === true) && (count($data) > 0))
		{
			$merge = array($merge, 'HAVING');

			foreach (array_combine(parent::Tick(array_keys($data)), parent::Quote($data)) as $key => $value)
			{
				if (is_array($value) === true)
				{
					if (preg_match('~\b(?:NOT\s+)?BETWEEN\b~i', $operator) > 0)
					{
						$value = sprintf('%s AND %s', array_shift($value), array_shift($value));
					}

					$value = (preg_match('~\b(?:NOT\s+)?IN\b~i', $operator) > 0) ? sprintf('(%s)', implode(', ', $value)) : array_shift($value);
				}

				$this->sql['having'][] = sprintf('%s %s %s %s', $merge[empty($this->sql['having'])], $key, $operator, $value);
			}
		}

		return $this;
	}

	public function Insert($table, $data, $replace = false)
	{
		$this->sql = array();

		if (is_object(phunction::DB()) === true)
		{
			$table = (is_array($table) !== true) ? explode(',', $table) : $table;

			if ((count($table = parent::Tick(array_filter(array_map('trim', $table), 'strlen'))) == 1) && (count($data) > 0))
			{
				$this->sql['query'] = sprintf('INSERT INTO %s', implode(', ', $table));

				if ($data !== array_values($data))
				{
					$this->sql['query'] .= sprintf(' (%s)', implode(', ', parent::Tick(array_keys($data))));
				}

				$this->sql['query'] .= sprintf(' VALUES (%s)', implode(', ', parent::Quote($data)));

				if (strcmp('pgsql', phunction::DB()->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0)
				{
					$this->sql['query'] .= sprintf(' RETURNING %s', parent::Tick('id'));
				}

				else if ((strcmp('mysql', phunction::DB()->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0) && (is_array($replace) === true) && (count($replace) > 0))
				{
					foreach ($replace as $key => $value)
					{
						$replace[$key] = sprintf('%s = %s', parent::Tick($key), parent::Quote($value));
					}

					$this->sql['query'] .= sprintf(' ON DUPLICATE KEY UPDATE %s', implode(', ', $replace));
				}

				else if ($replace === true)
				{
					$this->sql['query'] = substr_replace($this->sql['query'], 'REPLACE', 0, strlen('REPLACE') - 1);
				}
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
				$$value = (is_array($$value) !== true) ? explode(',', $$value) : $$value;
			}

			if (count($table = parent::Tick(array_filter(array_map('trim', $table), 'strlen'))) >= 1)
			{
				if (count($data = parent::Tick(array_filter(array_map('trim', $data), 'strlen'))) > 0)
				{
					if ($data !== array_values($data))
					{
						foreach ($data as $key => $value)
						{
							$data[$key] = sprintf('%s %s %s', parent::Tick($key), $operator, $value);
						}

						$this->sql['join'][] = sprintf('%s %s ON (%s)', trim($type . ' JOIN'), implode(', ', $table), implode(sprintf(' %s ', $merge), $data));
					}

					else
					{
						$this->sql['join'][] = sprintf('%s %s USING (%s)', trim($type . ' JOIN'), implode(', ', $table), implode(', ', $data));
					}
				}

				else if (empty($data) === true)
				{
					$this->sql['join'][] = sprintf('%s JOIN %s', trim('NATURAL ' . $type), implode(', ', $table));
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

	public function Literal($string)
	{
		if (is_object($result = new phunction_DB_SQL()) === true)
		{
			$result->sql['literal'] = trim($string);
		}

		return $result;
	}

	public function Order($data, $order = 'ASC')
	{
		if (array_key_exists('query', $this->sql) === true)
		{
			$data = (is_array($data) !== true) ? explode(',', $data) : $data;

			if (count($data = parent::Tick(array_filter(array_map('trim', $data), 'strlen'))) > 0)
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
				$$value = (is_array($$value) !== true) ? explode(',', $$value) : $$value;
			}

			if (count($data = parent::Tick(array_filter(array_map('trim', $data), 'strlen'))) > 0)
			{
				$this->sql['query'] = sprintf('SELECT %s', implode(', ', $data));

				if (count($table = parent::Tick(array_filter(array_map('trim', $table), 'strlen'))) >= 1)
				{
					$this->sql['query'] .= sprintf(' FROM %s', implode(', ', $table));
				}

				$this->sql['query'] = preg_replace('~^(SELECT)~', sprintf('%s', ($distinct === true) ? '$1 DISTINCT' : '$1'), $this->sql['query'], 1);
			}
		}

		return $this;
	}

	public function Update($table, $data)
	{
		$this->sql = array();

		if (is_object(phunction::DB()) === true)
		{
			$table = (is_array($table) !== true) ? explode(',', $table) : $table;

			if ((count($table = parent::Tick(array_filter(array_map('trim', $table), 'strlen'))) >= 1) && (count($data) > 0))
			{
				foreach ($data as $key => $value)
				{
					$data[$key] = sprintf('%s = %s', parent::Tick($key), parent::Quote($value));
				}

				$this->sql['query'] = sprintf('UPDATE %s SET %s', implode(', ', $table), implode(', ', $data));
			}
		}

		return $this;
	}

	public function Where($data, $operator = '=', $merge = 'AND')
	{
		if ((array_key_exists('query', $this->sql) === true) && (count($data) > 0))
		{
			$merge = array($merge, 'WHERE');

			foreach (array_combine(parent::Tick(array_keys($data)), parent::Quote($data)) as $key => $value)
			{
				if (is_array($value) === true)
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