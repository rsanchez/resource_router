<?php

namespace rsanchez\ResourceRouter;

use rsanchez\ResourceRouter\Router;

class Wildcard {
	/**
	 * The router who created this instance
	 */
	protected $router;

	/**
	 * The index of this wildcard in the parent router wildcards array
	 */
	protected $index;

	/**
	 * The value found in the URL for this matching wildcard.
	 */
	public $value;

	/**
	 * The type of wildcard, eg any, num, entry_id, etc.
	 */
	public $type;

	/**
	 * Meta data from the database, e.g. entry_id, url_title, etc.
	 * @var array|null
	 */
	protected $meta;

	public function __construct(Router $router, $index, $value, $type)
	{
		$this->router = $router;
		$this->index = $index;
		$this->value = $value;
		$this->type = $type;
	}

	/**
	 * Validate according to type
	 *
	 * @param array $where  additional columns to match in the query
	 * @return bool
	 */
	public function isValid($where = array())
	{
		switch($this->type)
		{
			case 'entry_id':
				$where['entry_id'] = $this->value;
				return $this->isValidEntry($where);
			case 'url_title':
				$where['url_title'] = $this->value;
				return $this->isValidEntry($where);
			case 'category_id':
				$where['cat_id'] = $this->value;
				return $this->isValidCategory($where);
			case 'category_url_title':
				$where['cat_url_title'] = $this->value;
				return $this->isValidCategory($where);
			case 'member_id':
				$where['member_id'] = $this->value;
				return $this->isValidMember($where);
			case 'username':
				$where['username'] = $this->value;
				return $this->isValidMember($where);
		}

		return true;
	}

	/**
	 * Check if the given category is valid
	 *
	 * if ($wildcard->isValidCategory(array(
	 * 	 'cat_url_title' => $wildcard,
	 * 	 'channel' => 'blog',
	 * ))
	 * {
	 *   $router->setTemplate('blog/category');
	 * }
	 * 
	 * @param  array   $where  where / where_in provided to CodeIgniter Active Record class
	 * @return boolean|mixed is this a valid category|the value of the $return column, if specified
	 */
	public function isValidCategory(array $where)
	{
		$joined = FALSE;	

		if (isset($where['channel']) || isset($where['channel_id']))
		{
			if (isset($where['channel']))
			{
				$channel = is_array($where['channel']) ? $where['channel'] : array($where['channel']);

				ee()->db->where_in('channel_name', $channel);
			}

			if (isset($where['channel_id']))
			{
				$channel_id = is_array($where['channel_id']) ? $where['channel_id'] : array($where['channel_id']);

				ee()->db->where_in('channel_id', $channel_id);
			}

			ee()->db->select('cat_group');

			$query = ee()->db->get('channels');

			if ($query->num_rows() > 0 && ! isset($where['group_id']))
			{
				$where['group_id'] = array();
			}

			foreach ($query->result() as $row)
			{
				foreach (explode('|', $row->cat_group) as $group_id)
				{
					$where['group_id'][] = $group_id;
				}
			}

			unset($where['channel'], $where['channel_id']);			
		}

		foreach ($where as $key => $value)
		{
			if ($joined === FALSE && strncmp($key, 'field_id_', 9) === 0)
			{
				ee()->db->join('category_field_data', 'category_field_data.cat_id = categories.cat_id');

				$joined = TRUE;
			}

			if ($key === 'cat_id')
			{
				$key = 'categories.cat_id';
			}

			if (is_array($value))
			{
				ee()->db->where_in($key, $value);
			}
			else
			{
				ee()->db->where($key, (string) $value);
			}
		}

		$select = array(
			'cat_id',
			'site_id',
			'group_id',
			'parent_id',
			'cat_name',
			'cat_url_title',
			'cat_description',
			'cat_image',
			'cat_order',
		);

		foreach ($select as $column)
		{
			ee()->db->select('categories.'.$column);
		}

		$query = ee()->db->get('categories');

		$isValid = $query->num_rows() > 0;

		$meta = $isValid ? $query->row_array() : array_fill_keys($select, null);

		$this->setMeta($meta);

		$query->free_result();

		return $isValid;
	}

	/**
	 * Check if the given entry is valid
	 *
	 * if ($wildcard->isValidEntry(array(
	 * 	 'url_title' => $wildcard,
	 * 	 'channel' => 'blog',
	 * 	 'status' => 'open',
	 * ))
	 * {
	 *   $router->setTemplate('blog/detail');
	 * }
	 * 
	 * @param  array   $where  where / where_in provided to CodeIgniter Active Record class
	 * @return boolean is this a valid entry
	 */
	public function isValidEntry(array $where)
	{
		$joined_data = FALSE;
		$joined_channel = FALSE;

		foreach ($where as $key => $value)
		{
			if ($joined_data === FALSE && strncmp($key, 'field_id_', 9) === 0)
			{
				ee()->db->join('channel_data', 'channel_data.entry_id = channel_titles.entry_id');

				$joined_data = TRUE;
			}

			if ($key === 'channel' || $key === 'channel_name')
			{
				if ($joined_channel === FALSE)
				{
					ee()->db->join('channels', 'channels.channel_id = channel_titles.channel_id');

					$joined_channel = TRUE;
				}

				$key = 'channels.channel_name';
			}

			if ($key === 'channel_id')
			{
				$key = 'channel_titles.channel_id';
			}

			if ($key === 'entry_id')
			{
				$key = 'channel_titles.entry_id';
			}

			if (is_array($value))
			{
				ee()->db->where_in($key, $value);
			}
			else
			{
				ee()->db->where($key, (string) $value);
			}
		}

		$select = array(
			'entry_id',
			'title',
			'url_title',
			'channel_id',
		);

		foreach ($select as $column)
		{
			ee()->db->select('channel_titles.'.$column);
		}

		$query = ee()->db->get('channel_titles');

		$isValid = $query->num_rows() > 0;

		$meta = $isValid ? $query->row_array() : array_fill_keys($select, null);

		$this->setMeta($meta);

		$query->free_result();

		return $isValid;
	}

	/**
	 * Check if the given member is valid
	 *
	 * if ($wo;dcard->isValidMember(array(
	 * 	 'username' => $wildcard,
	 * 	 'group_id' => 6,
	 * ))
	 * {
	 *   $router->setTemplate('users/detail');
	 * }
	 * 
	 * @param  array   $where  where / where_in provided to CodeIgniter Active Record class
	 * @return boolean is this a valid member
	 */
	public function isValidMember(array $where)
	{
		foreach ($where as $key => $value)
		{
			if (is_array($value))
			{
				ee()->db->where_in($key, $value);
			}
			else
			{
				ee()->db->where($key, (string) $value);
			}
		}

		$select = array(
			'member_id',
			'group_id',
			'email',
			'username',
			'screen_name',
		);

		ee()->db->select($select);

		$query = ee()->db->get('members');

		$isValid = $query->num_rows() > 0;

		$meta = $isValid ? $query->row_array() : array_fill_keys($select, null);

		$this->setMeta($meta);

		$query->free_result();

		return $isValid;
	}
 
	/**
	 * Check if the given category ID is valid
	 *
	 * if ($wildcard->isValidCategoryId())
	 * {
	 *   $router->setTemplate('blog/category');
	 * }
	 * 
	 * @param array $where additional columns to add to the sql query
	 * @return boolean is this a valid category
	 */
	public function isValidCategoryId($where = array())
	{
		$where['cat_id'] = $this->value;

		return $this->isValidCategory($where);
	}
 
	/**
	 * Check if the given category url title is valid
	 *
	 * if ($wildcard->isValidCategoryUrlTitle())
	 * {
	 *   $router->setTemplate('blog/category');
	 * }
	 * 
	 * @param array $where additional columns to add to the sql query
	 * @return boolean is this a valid category
	 */
	public function isValidCategoryUrlTitle($where = array())
	{
		$where['cat_url_title'] = $this->value;

		return $this->isValidCategory($where);
	}
 
	/**
	 * Check if the given entry id is valid
	 *
	 * if ($wildcard->isValidEntryId())
	 * {
	 *   $router->setTemplate('blog/detail');
	 * }
	 * 
	 * @param array $where additional columns to add to the sql query
	 * @return boolean is this a valid entry
	 */
	public function isValidEntryId($where = array())
	{
		$where['entry_id'] = $this->value;

		return $this->isValidEntry($where);
	}
 
	/**
	 * Check if the given member_id is valid
	 *
	 * if ($wildcard->isValidMemberId())
	 * {
	 *   $router->setTemplate('users/detail');
	 * }
	 * 
	 * @param array $where additional columns to add to the sql query
	 * @return boolean is this a valid member
	 */
	public function isValidMemberId($where = array())
	{
		$where['member_id'] = $this->value;

		return $this->isValidMember($where);
	}
 
	/**
	 * Check if the given url title is valid
	 *
	 * if ($wildcard->isValidUrlTitle())
	 * {
	 *   $router->setTemplate('blog/detail');
	 * }
	 * 
	 * @param array $where additional columns to add to the sql query
	 * @return boolean is this a valid entry
	 */
	public function isValidUrlTitle($where = array())
	{
		$where['url_title'] = $this->value;

		return $this->isValidEntry($where);
	}
 
	/**
	 * Check if the given username is valid
	 *
	 * if ($wildcard->isValidUsername())
	 * {
	 *   $router->setTemplate('users/detail');
	 * }
	 * 
	 * @param array $where additional columns to add to the sql query
	 * @return boolean is this a valid member
	 */
	public function isValidUsername($where = array())
	{
		$where['username'] = $this->value;

		return $this->isValidMember($where);
	}

	/**
	 * Get data from this wildcard's DB table (if applicable)
	 * @param  string $column ex. entry_id
	 * @return string|null
	 */
	public function getMeta($column)
	{
		return isset($this->meta[$column]) ? $this->meta[$column] : null;
	}

	/**
	 * Set the meta for this validating wildcard (if applicable)
	 * @param array $meta
	 * @return void
	 */
	protected function setMeta(array $meta)
	{
		$this->meta = $meta;

		foreach ($this->meta as $key => $value)
		{
			$this->router->setGlobal(sprintf('route_%d_%s', $this->index, $key), $value);
		}
	}

	public function compare($operator, $value)
	{
		switch ($operator) {
			case '==':
				return $this->value == $value;
			case '===':
				return $this->value === $value;
			case '!=':
				return $this->value != $value;
			case '<>':
				return $this->value <> $value;
			case '!==':
				return $this->value !== $value;
			case '>':
				return $this->value > $value;
			case '>=':
				return $this->value >= $value;
			case '<':
				return $this->value < $value;
			case '<=':
				return $this->value <= $value;
		}

		throw new \Exception('Invalid comparison operator.');
	}

	public function is($value)
	{
		return $this->compare('==', $value);
	}

	public function in(array $where)
	{
		return in_array($this->value, $where);
	}

	public function isExactly($value)
	{
		return $this->compare('===', $value);
	}

	public function isNot($value)
	{
		return $this->compare('!=', $value);
	}

	public function isNotExactly($value)
	{
		return $this->compare('!==', $value);
	}

	public function isGreaterThan($value)
	{
		return $this->compare('>', $value);
	}

	public function isGreaterThanOrEqual($value)
	{
		return $this->compare('>=', $value);
	}

	public function isLessThan($value)
	{
		return $this->compare('<', $value);
	}

	public function isLessThanOrEqual($value)
	{
		return $this->compare('<=', $value);
	}

	public function isEmpty()
	{
		return ! $this->value && $this->value !== '0';
	}

	public function __toString()
	{
		return (string) $this->value;
	}
}