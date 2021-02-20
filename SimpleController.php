<?php
/*
 * 所有函数的$filter参数都需要按照以下两种格式之一来书写，之后会由getFilterArray自动处理，不是数组不作处理
 * 其中字符串值要被转义引号\'包围
 * array('字段名'=>'字段值')    array('id'=>1)
 * array('字段名'=>array('connect'=>'连接方式，默认and','type'=>'搜索方式，默认=','value'=>'字段值'))    array('connect'=>'and','name'=>array('type'=>'like','value'=>'%aaa%'))
 * 所有函数的$options参数通过getOptions函数获得
 * resultToPHPArray函数可以将f3返回的object数组转化为php可用的数据数组，在php中供调试使用
 */

class SimpleController
{
	private $mapper;

    /**
     * @var 表名
     */
    private $name;

    /**
     * 构造函数
     * @param $name 表名
     */
    public function __construct($name)
    {
        $this->setTable($name);
    }

    /**
     * 为本控制器设置一个表
     * @param $name 表名
     */
    public function setTable($name)
    {
        global $f3;
        $this->name = $name;
        $this->mapper = new DB\SQL\Mapper($f3->get('DB'),$this->name);
    }

    /**
     * 获取表名
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * find函数
     * @param array $filter
     * @param array $options
     * @return mixed
     */
    public function find($filter = NULL, $options = NULL)
    {
        $arr = $this->mapper->find($this->getFilterArray($filter),$options);
        return $this->f3ResultToPHPArray($arr);
    }

    /**
     * select函数
     * @param $fields
     * @param array $filter
     * @param array $options
     * @return mixed
     */
    public function select($fields, $filter = NULL, $options = NULL)
    {
        $arr = $this->mapper->select($fields,$this->getFilterArray($filter),$options);
        return $this->f3ResultToPHPArray($arr,$fields);
    }

    /**
     * selectInTables函数，多表联查函数
     * @param array $tables 多表的表名数组 如 array('test','test2','test3')
     * @param array $keys 多表的key对应关秀 如 array(array('test'=>'A','test2'=>'id'),array('test2'=>'name','test3'=>'sname'))
     * @return mixed
     */
    public static function selectInTables($tables,$keys,$filter = NULL)
    {
        $sql = 'select ';
        for($i = 0;$i<count($tables);$i++)
        {
            if($i>0)
                $sql.=',';
            $sql.= 't'.$i.'.*';
        }
        $sql.=' from ';
        for($i = 0;$i<count($tables);$i++)
        {
            if($i>0)
                $sql.=' join ';
            $sql.= $tables[$i].' t'.$i;
        }
        $sql.=' on ';
        for($i = 0;$i<count($keys);$i++)
        {
            if($i>0)
                $sql.=' and ';
            $j=0;
            foreach($keys[$i] as $k=>$v)
            {
                if($j>0)
                    $sql.='=';
                $sql.= 't'.array_search($k,$tables).'.'.$v;
                $j++;
            }
        }
        $sql.=' where ';
        $sql.= self::getFilterText($filter);

        //return $sql;
        return self::execute($sql);
    }

    /**
     * insert函数
     * @param array|string $data
     */
    public function insert($data)
    {
        $this->mapper->copyfrom($data);
        $this->mapper->insert();
    }

    /**
     * update函数
     * @param array $filter
     * @param array|string $data
     */
    public function update($filter, $data)
    {
        $this->mapper->load($this->getFilterArray($filter));
        $this->mapper->copyfrom($data);
        $this->mapper->update();
    }

    /**
     * delete函数
     * @param array $filter
     */
    public function delete($filter)
    {
        $this->mapper->erase($this->getFilterArray($filter));
    }

    /**
     * 得到一个f3可用的options数组
     * @param string $order
     * @param string $limit
     * @param int $offset
     * @param string $group
     * @return array
     */
    public function getOptions($order, $limit, $offset = 0, $group = '')
	{
        return array(
            'order' => $order,
            'group' => $group,
            'limit' => $limit,
            'offset' => $offset
        );
    }

    /**
     * exec函数
     * @param string $sql
     * @return mixed
     */
    public static function execute($sql)
    {
        global $f3;
        return $f3->get('DB')->exec($sql);
    }

    /**
     * 获取一个表的结构
     * @param string $name
     * @return mixed
     */
    public static function getTableStruct($name)
    {
        return self::execute('select COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT from information_schema.COLUMNS where table_name = "'.$name.'"');
    }

    /**
     * 获取所有的表名
     * @return mixed
     */
    public static function getAllTableNames()
    {
        return self::execute('SHOW TABLES');
    }

    /**
     * 查找一个表是否存在
     * @param string $name
     * @return bool
     */
    public static function hasTable($name)
    {
        return self::execute('SHOW TABLES LIKE \''.$name.'\'')!=null;
    }

    /**
     * 通过用户名登录
     * @param $user
     * @param $pwd
     * @return bool
     */
    public function loginUsername($user, $pwd)
    {		// very simple login -- no use of encryption, hashing etc.
        $auth = new \Auth($this->mapper, array('id'=>'username', 'pw'=>'password'));	// fields in table
        return $auth->login($user, $pwd); 			// returns true on successful login
    }

    /**
     * 通过email登录
     * @param $email
     * @param $pwd
     * @return bool
     */
    public function loginEmail($email, $pwd)
    {		// very simple login -- no use of encryption, hashing etc.
        $auth = new \Auth($this->mapper, array('id'=>'email', 'pw'=>'password'));	// fields in table
        return $auth->login($email, $pwd); 			// returns true on successful login
    }

    /**
     * 将php数组转化为f3可用形式，不是数组不作处理
     * 其中字符串值要被转义引号\'包围
     * array('字段名'=>'字段值')    array('id'=>1)
     * array('字段名'=>array('connect'=>'连接方式，默认and','type'=>'搜索方式，默认=','value'=>'字段值'))    array('connect'=>'and','name'=>array('type'=>'like','value'=>'%aaa%'))
     * @param array $arr
     * @return array|null
     */
    private function getFilterArray($arr)
    {
        if($arr == NULL)
            return NULL;
        if(!is_array($arr))
            return $arr;

        $filter = '';
        $query = array();
        $i = 0;
        foreach($arr as $key=>$value)
        {
            if(is_array($value))
            {
                if($i>0)
                {
                    if(array_key_exists('connect',$value))
                        $filter .= ' '.$value['connect'].' ';
                    else
                        $filter .= ' and ';
                }

                if(array_key_exists('type',$value))
                    $filter = $filter.$key.' '.$value['type'].' ?';
                else
                    $filter = $filter.$key.' = ?';
                if(array_key_exists('value',$value))
                    array_push($query,$value['value']);
                else
                    array_push($query,$value);
            }
            else{
                $filter = $filter.$key.' = ?';
                array_push($query,$value);
            }

            $i = $i + 1;
        }
        array_unshift($query,$filter);

        return $query;
    }

    /**
     * 将php数组转化为字符串，不是数组不作处理
     * 其中字符串值要被转义引号\'包围
     * array('字段名'=>'字段值')    array('id'=>1)
     * array('字段名'=>array('connect'=>'连接方式，默认and','type'=>'搜索方式，默认=','value'=>'字段值'))    array('connect'=>'and','name'=>array('type'=>'like','value'=>'%aaa%'))
     * @param array $arr
     * @return string
     */
    private static function getFilterText($arr)
    {
        if($arr == NULL)
            return '';
        if(!is_array($arr))
            return $arr;

        $filter = '';
        $i = 0;
        foreach($arr as $key=>$value)
        {
            if(is_array($value))
            {
                if($i>0)
                {
                    if(array_key_exists('connect',$value))
                        $filter .= ' '.$value['connect'].' ';
                    else
                        $filter .= ' and ';
                }

                if(array_key_exists('type',$value))
                    $filter .= $key.' '.$value['type'].' ';
                else
                    $filter .= $key.' = ';
                if(array_key_exists('value',$value))
                    $filter .= $value['value'];
                else
                    $filter .= $value;
            }
            else{
                $filter .= $key.' = '.$value;
            }

            $i++;
        }

        return $filter;
    }

    /**
     * 将f3的sql搜索结果转化为php数组
     * @param array $arr
     * @param string $filter
     * @return array|null
     */
    private function f3ResultToPHPArray($arr,$filter = NULL)
    {
        if($arr==NULL)
            return NULL;

        $filters = array();
        if($filter != NULL )
        {
            $filters = explode(',',$filter);
            for($i=0;$i<count($filters);$i++)
                $filters[$i] = trim($filters[$i]);
        }

        $re = array();
        foreach($arr as $key=>$value)
        {
            $fileds = array();
            $t_value = (array)$value;
            foreach($t_value as $ke=>$val)
            {
                if(strpos($ke, 'fields'))
                {
                    $t_val = (array)$val;
                    foreach($t_val as $k=>$v)
                    {
                        if(empty($filters)|| in_array($k,$filters))
                            $fileds[$k] = ((array)$v)['value'];
                    }
                }
            }
            array_push($re,$fileds);
        }
        return $re;
    }
}

?>
