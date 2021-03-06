<?php
/**
 * 精简版Smarty视图引擎
 * 缓存类型：文本
 * @system UNPHP 
 * @version UNPHP 1.0
 * @author Xiao Tangren  <unphp@qq.com>
 * @data 2014-03-05
 * */
class Ext_View_FileSmarty extends UnPHP_View_Abstract
{

        // 模板文件目录
        public $template_dir = '';
        // 静态缓存目录
        public $cache_dir = '';
        // 编译缓存目录
        public $compile_dir = '';
        // 缓存更新时间, 默认 3600 秒
        public $cache_lifetime = 3600;
        // 不缓存，直接输出。。。
        public $direct_output = true;
        // 是否开启静态缓存
        public $caching = true;
        // 是否开启编译缓存
        public $force_compile = true;
        public $template = array();
        public $_var = array();
        public $_UnPHPhash = '554fcae493e564ee0dc75bdf2ebf94ca_xiaotangren';
        public $_foreach = array();
        public $_current_file = '';
        public $_expires = 0;
        public $_errorlevel = 0;
        public $_nowtime = null;
        public $_checkfile = true;
        public $_foreachmark = '';
        public $_seterror = 0;
        public $_temp_key = array();  // 临时存放 foreach 里 key 的数组
        public $_temp_val = array();  // 临时存放 foreach 里 item 的数组
        public $filename = array();
        // 静态缓存文件“是否存在”
        protected $cached = false;
        protected $_start_tag = '{';
        protected $_end_tag = '}';
        static public $smarty = null;

        public function init($conf = array())
        {
                $this->_errorlevel = error_reporting();
                $this->_nowtime = time();
                $this->cache_lifetime = isset($conf['lifetime']) ? $conf['lifetime'] : 3600;
                $this->direct_output = !(isset($conf['caching']) ? $conf['caching'] : true);  // 不缓存，直接输出。。。
                // 所谓编译缓存，指将模板smarty标签，翻译成php和html混合的文件后，生成的缓存。
                $this->force_compile = isset($conf['compileCaching']) ? $conf['compileCaching'] : true;   // 默认开启编译缓存
                $this->caching = isset($conf['htmlCaching']) ? $conf['htmlCaching'] : false;   // 默认开启静态缓存
                if(!$this->direct_output && $this->caching){
                       $this->cache_dir = rtrim($conf['tempPath'], '/\\')  . DIRECTORY_SEPARATOR.'html' . DIRECTORY_SEPARATOR;
                }
                if(!$this->direct_output && $this->force_compile){
                       $this->compile_dir = rtrim($conf['tempPath'], '/\\')  . DIRECTORY_SEPARATOR.'compile' . DIRECTORY_SEPARATOR;
                }
                
                $this->_start_tag = $this->escapeTag(isset($conf['startTag']) ? $conf['startTag'] : '{');   // 开始标签
                $this->_end_tag = $this->escapeTag(isset($conf['endTag']) ? $conf['endTag'] : '}');         // 结束标签
                
                if(!is_readable($conf['tempPath'])){
                        if (!@mkdir($conf['tempPath'], 0777, TRUE)) {
                                throw new SmartyException('Invalid path provided,in that themes\'s dir of " '.$conf['tempPath'].' "!');
                        }
                }
                if (!is_readable($this->cache_dir)) {
                    if (!@mkdir($this->cache_dir, 0777, TRUE)) {
                        throw new SmartyException('There\'s not enough permissions for create file, in that temp\'s dir of ' . $conf['tempPath'] . '!');
                    }
                }
                if (!is_readable($this->compile_dir)) {
                    if (!@mkdir($this->compile_dir, 0777, TRUE)) {
                        throw new SmartyException('There\'s not enough permissions for create file, in that temp\'s dir of ' . $conf['tempPath'] . '!');
                    }
                }
                return true;
        }

        /**
         * Set the path to the templates
         *
         * @param string $path The directory to set as the path.
         * @return void
         */
        public function setScriptPath($path)
        {
                try
                {
                        if (is_readable($path))
                        {
                                $this->template_dir = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
                                return;
                        }
                }
                catch (Exception $exc)
                {
                        throw new SmartyException('Invalid path provided,in that themes\'s dir of " ' . $path . ' "!'.$exc->getMessage());
                }
        }

        /**
         * Retrieve the current template directory
         *
         * @return string
         */
        public function getScriptPath()
        {
                return $this->template_dir;
        }

        /**
         * Processes a template and returns the output.
         *
         * @param string $name The template to process.
         * @return string The output.
         */
        public function render($name, $value = NULL)
        {
                return $this->fetch($name, $value);
        }

        /**
         * 处理字符串函数
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $source
         * @return  sring
         */
        public function fetch_str($source)
        {
                $source = preg_replace("/<\?[^><]+\?>|<\%[^><]+\%>|<script[^>]+language[^>]*=[^>]*php[^>]*>[^><]*<\/script\s*>/iU", "", $source);
                //smarty的注释语法

                $source = preg_replace("/" . $this->_start_tag . "\*.*?\*" . $this->_end_tag . "/is", "", $source);
                //$source = preg_replace("/<\%[^><]+\%>|<script[^>]+language[^>]*=[^>]*php[^>]*>[^><]*<\/script\s*>/iU", "", $source);
                //smarty 起始标签
                return preg_replace_callback("/" . $this->_start_tag . "([^" . $this->_start_tag . $this->_end_tag . "\n]*)" . $this->_end_tag . "/", "self::callback_select", $source);
        }

        /**
         * 注册变量
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   mix      $tpl_var
         * @param   mix      $value
         * @return  void
         */
        public function assign($tpl_var, $value = '')
        {
                if (is_array($tpl_var))
                {
                        foreach ($tpl_var AS $key => $val)
                        {
                                if ($key != '')
                                {
                                        $this->_var[$key] = $val;
                                }
                        }
                }
                else
                {
                        if ($tpl_var != '')
                        {
                                $this->_var[$tpl_var] = $value;
                        }
                }
        }

        /**
         * 显示页面函数
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string      $filename
         * @param   sting      $cache_id
         * @return  void
         */
        public function display($filename, $cache_id = '')
        {
                $this->_seterror++;
                //error_reporting(E_ALL ^ E_NOTICE);
                //$this->_checkfile = false;
                $out = $this->fetch($filename, $cache_id);
                if (strpos($out, $this->_UnPHPhash) !== false)
                {
                        $k = explode($this->_UnPHPhash, $out);
                        foreach ($k AS $key => $val)
                        {
                                if (($key % 2) == 1)
                                {
                                        $k[$key] = $this->insert_mod($val);
                                }
                        }
                        $out = implode('', $k);
                }
                error_reporting($this->_errorlevel);
                $this->_seterror--;
                echo $out;
        }

        /**
         * 处理模板文件
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string      $filename
         * @param   sting      $cache_id
         * @return  sring
         */
        public function fetch($filename, $cache_id = '')
        {
                if (!$this->_seterror)
                {
                        error_reporting(E_ALL ^ E_NOTICE);
                }
                $this->_seterror++;
                if (strncmp($filename, 'str:', 4) == 0)
                {
                        $out = $this->_eval($this->fetch_str(substr($filename, 4)));
                }
                else
                {
                        $filename_cp = $filename;
                        //-------------------------------------------------
                        //检测模板文件是否存在
                        if ($this->_checkfile)
                        {
                                if (!file_exists($filename))
                                {
                                        $filename = $this->template_dir . $filename;
                                }
                        }
                        else
                        {
                                $filename = $this->template_dir . $filename;
                        }
                        $this->filename[$filename] = $filename_cp;
                        //--------------------------------------------------
                        //不作任何缓存（编译缓存，静态缓存），直接输出。。。
                        if ($this->direct_output)
                        {
                                $this->_current_file = $filename;
                                $out = $this->_eval($this->fetch_str(file_get_contents($filename)));
                        }
                        //进行缓存
                        else
                        {
                                // 如果有静态缓存，并且设置了缓存，则直接输出。
                                if ($cache_id && $this->caching && $this->cached)
                                {
                                        $out = $this->template_out;
                                }
                                // 否则编译生成。
                                else
                                {
                                        if (!in_array($filename, $this->template))
                                        {
                                                $this->template[] = $filename;
                                        }
                                        // 进行编译
                                        $out = $this->make_compiled($filename);
                                        // 进行静态缓存
                                        if ($this->caching)
                                        {
                                                $out = $this->doCreateCache($filename_cp, $cache_id, str_replace("\r", '', $out));
                                                $this->template = array();
                                        }
                                }
                        }
                }
                $this->_seterror--;
                if (!$this->_seterror)
                {
                        error_reporting($this->_errorlevel);
                }
                return $out; // 返回html数据
        }

        /**
         * 编译模板函数
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string      $filename
         * @return  sring        编译后文件内容
         */
        public function make_compiled($filename)
        {
                if ($this->force_compile == false)
                {
                        $this->_current_file = $filename;
                        $source = $this->_eval($this->fetch_str(file_get_contents($filename)));
                }
                else
                {
                        $compile_dir_file = $this->filename[$filename];
                        $name = $this->compile_dir . $compile_dir_file . '.php';
                        $expires = 0;
                        if (file_exists($name))
                        {
                                $filestat = @stat($name);
                                $expires = $filestat['mtime'];
                        }
                        //-----------------------------------
                        // 模板文件日期等信息
                        $filestat = @stat($filename);
                        // 判断模板是否更新：对比“缓存生成时间”和“模板修改时间”
                        if ($filestat['mtime'] <= $expires)
                        {
                                $source = $this->_require($name);
                        }
                        // 生成（或覆盖）编译缓存。
                        else
                        {
                                $this->_current_file = $filename;
                                $fetch_str = $this->fetch_str(file_get_contents($filename));
                                $this->autoMkdir($name);
                                if (file_put_contents($name, $fetch_str, LOCK_EX) === false)
                                {
                                        trigger_error('can\'t write:' . $name);
                                }
                                $source = $this->_eval($fetch_str);
                        }
                }
                // 返回编译内容
                return $source;
        }

        /**
         * 
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @param type $content
         * @return type
         */
        protected function _eval($content)
        {
                ob_start();
                eval('?' . '>' . trim($content));
                $content = ob_get_contents();
                ob_end_clean();

                return $content;
        }

        /**
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @param type $filename
         * @return type
         */
        protected function _require($filename)
        {
                ob_start();
                include $filename;
                $content = ob_get_contents();
                ob_end_clean();

                return $content;
        }

        /**
         * 判断是否缓存
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $filename
         * @param   sting      $cache_id
         * @return  bool
         */
        public function is_cached($filename, $cache_id = '')
        {
                if ($this->caching == true && $this->direct_output == false)
                {
                        $this->cached = true;
                        $cachefile = $this->getCachefilepath($filename, $cache_id);
                        if (file_exists($cachefile) && $data = @file_get_contents($cachefile))
                        {
                                $data = substr($data, 13);
                                $pos = strpos($data, '<');
                                $paradata = substr($data, 0, $pos);
                                $para = @unserialize($paradata);
                                // 如果 “现在的时间”>缓存“过期时间” ，那么，缓存失效！
                                if ($para === false || $this->_nowtime > $para['expires'])
                                {
                                        $this->cached = false;
                                        return false;
                                }
                                $this->_expires = $para['expires'];
                                $this->template_out = substr($data, $pos);
                                // 遍历加载进来的每个“子模板”，对比“子模板”修改时间与缓存创建时间。
                                foreach ($para['template'] AS $val)
                                {
                                        $stat = @stat($val);
                                        // 如果“子模板”修改时间 大于 “缓存创建时间”，那么，缓存失效！
                                        if ($para['maketime'] < $stat['mtime'])
                                        {
                                                $this->cached = false;
                                                return false;
                                        }
                                }
                        }
                        else
                        {
                                $this->cached = false;
                                return false;
                        }
                        return true;
                }
                else
                {
                        return false;
                }
        }

        /**
         * 处理{}标签
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string      $tag
         * @return  sring
         */
        protected function select($tag)
        {
                $tag = stripslashes(trim($tag));
                if (empty($tag))
                {
                        return '{}';
                }
                // 注释部分
                elseif ($tag{0} == '*' && substr($tag, -1) == '*')
                {
                        return '';
                }
                // 变量
                elseif ($tag{0} == '$')
                {
                        if ($tag{1} != '(') // 避免与JQuery中的选择器---"{$" 冲突
                                return '<?php echo ' . $this->get_val(substr($tag, 1)) . '; ?>';
                }
                // 结束 tag
                elseif ($tag{0} == '/')
                {
                        switch (substr($tag, 1))
                        {
                                case 'if':
                                        return '<?php endif; ?>';
                                        break;

                                case 'foreach':
                                        if ($this->_foreachmark == 'foreachelse')
                                        {
                                                $output = '<?php endif; unset($_from); ?>';
                                        }
                                        else
                                        {
                                                array_pop($this->_patchstack);
                                                $output = '<?php endforeach; endif; unset($_from); ?>';
                                        }
                                        $output .= "<?php \$this->pop_vars();; ?>";

                                        return $output;
                                        break;

                                case 'literal':
                                        return '';
                                        break;
                                case '_php':
                                        return ' ?>';
                                        break;
                                default:
                                        return '{' . $tag . '}';
                                        break;
                        }
                }
                //其他标签
                else
                {
                        //else if 标签
                        if ('else if' === (substr($tag, 0, 7)))
                        {
                                return $this->_compile_if_tag(substr($tag, 7), true);
                        }
                        $tag_temp = explode(' ', $tag);  //  分割成数组
                        $tag_sel = array_shift($tag_temp); // 取出数组中第一个元素，并从数组中删除它
                        switch ($tag_sel)
                        {
                                case 'if':

                                        return $this->_compile_if_tag(substr($tag, 3));
                                        break;

                                case 'else':

                                        return '<?php else: ?>';
                                        break;

                                case 'elseif':

                                        return $this->_compile_if_tag(substr($tag, 7), true);
                                        break;

                                case 'foreachelse':
                                        $this->_foreachmark = 'foreachelse';

                                        return '<?php endforeach; else: ?>';
                                        break;

                                case 'foreach':
                                        $this->_foreachmark = 'foreach';
                                        if (!isset($this->_patchstack))
                                        {
                                                $this->_patchstack = array();
                                        }
                                        return $this->_compile_foreach_start(substr($tag, 8));
                                        break;

                                case 'assign':
                                        $t = $this->get_para(substr($tag, 7), 0);
                                        if ($t['value']{0} == '$')
                                        {
                                                /* 如果传进来的值是变量，就不用用引号 */
                                                $tmp = '$this->assign(\'' . $t['var'] . '\',' . $t['value'] . ');';
                                        }
                                        else
                                        {
                                                $tmp = '$this->assign(\'' . $t['var'] . '\',\'' . addcslashes($t['value'], "'") . '\');';
                                        }
                                        // $tmp = $this->assign($t['var'], $t['value']);

                                        return '<?php ' . $tmp . ' ?>';
                                        break;

                                case 'include':
                                        //$t = $this->get_para(substr($tag, 8), 1);
                                        //修复file=$xxx不支持变量的bug
                                        $t = $this->get_para(substr($tag, 8), 0);
                                        if (preg_match('/file[\s]?=[\s]?\$/ie', substr($tag, 8)))
                                        {
                                                return '<?php echo $this->fetch(' . "{$t['file']}" . '); ?>';
                                        }
                                        else
                                        {
                                                return '<?php echo $this->fetch(' . "'{$t['file']}'" . '); ?>';
                                        }
                                        break;

                                case 'insert_scripts':
                                        $t = $this->get_para(substr($tag, 15), 0);
                                        return '<?php echo $this->smarty_insert_scripts(' . $this->make_array($t) . '); ?>';
                                        break;

                                case 'create_pages':
                                        $t = $this->get_para(substr($tag, 13), 0);
                                        return '<?php echo $this->smarty_create_pages(' . $this->make_array($t) . '); ?>';
                                        break;

                                case 'insert' :
                                        $t = $this->get_para(substr($tag, 7), 0);
                                        $out = "<?php \n" . '$k = ' . preg_replace_callback("/(\'\\$[^,]+)/", function($match)
                                                {
                                                        return stripslashes(trim($match[1], '\''));
                                                }, var_export($t, true)) . ";\n";
                                        $out .= 'echo $this->_UnPHPhash . $k[\'name\'] . \'|\' . serialize($k) . $this->_UnPHPhash;' . "\n?>";
                                        return $out;
                                        break;

                                case 'literal':
                                        return '';
                                        break;

                                case 'cycle' :
                                        $t = $this->get_para(substr($tag, 6), 0);
                                        return '<?php echo $this->cycle(' . $this->make_array($t) . '); ?>';
                                        break;

                                case 'html_options':
                                        $t = $this->get_para(substr($tag, 13), 0);
                                        return '<?php echo $this->html_options(' . $this->make_array($t) . '); ?>';
                                        break;

                                case 'html_select_date':
                                        $t = $this->get_para(substr($tag, 17), 0);
                                        return '<?php echo $this->html_select_date(' . $this->make_array($t) . '); ?>';
                                        break;

                                case 'html_radios':
                                        $t = $this->get_para(substr($tag, 12), 0);
                                        return '<?php echo $this->html_radios(' . $this->make_array($t) . '); ?>';
                                        break;

                                case 'html_select_time':
                                        $t = $this->get_para(substr($tag, 12), 0);
                                        return '<?php echo $this->html_select_time(' . $this->make_array($t) . '); ?>';
                                        break;

                                case '_php':
                                        return '<?php ';
                                        break;
                                case 'num_float_rand':
                                        $t = $this->get_para(substr($tag, 9), 0);
                                        $min = intval($t['min']);
                                        $max = intval($t['max']);
                                        $f = intval($t['f']);
                                        return '<?php printf("%.' . $f . 'f",' . $min . '+ mt_rand() / mt_getrandmax()*(' . $max . '-' . $min . ')); ?>';
                                        break;
                                default:
                                        return '{' . $tag . '}';
                                        break;
                        }
                }
        }

        /**
         * 处理insert外部函数/需要include运行的函数的调用数据
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $val
         * @param   int         $type
         * @return  array
         */
        protected function get_para($val, $type = 1) // 处理insert外部函数/需要include运行的函数的调用数据
        {
                $pa = $this->str_trim($val);
                foreach ($pa AS $value)
                {
                        if (strrpos($value, '='))
                        {
                                list($a, $b) = explode('=', str_replace(array(' ', '&quot;'), '', $value));
                                if ($b{0} == '$')
                                {
                                        if ($type)
                                        {
                                                eval('$para[\'' . $a . '\']=' . $this->get_val(substr($b, 1)) . ';');
                                        }
                                        else
                                        {
                                                $para[$a] = $this->get_val(substr($b, 1));
                                        }
                                }
                                else
                                {
                                        $para[$a] = str_replace(array('"', "'"), '', $b);
                                }
                        }
                }
                return $para;
        }

        /**
         * 处理smarty标签中的变量标签
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $val
         * @return  bool
         */
        protected function get_val($val)
        {
//                var_dump($val);exit;
                if (strrpos($val, '[$') !== false)
                {
                        $val = preg_replace_callback("/\[(\$[^\$\[\]]*)\]/is", function($match)
                        {
                                return '[' . str_replace('$', '\$', $match[1]) . ']';
                        }, $val);
                }
                if (strrpos($val, "['") !== false || strrpos($val, '["') !== false)
                {
                        $val = preg_replace_callback("/\[[\']([^\[\]]*)[\']\]/is", function($match)
                        {
                                return "." . strval($match[1]);
                        }, $val);
                        $val = preg_replace_callback("/\[[\"]([^\[\]]*)[\"]\]/is", function($match)
                        {
                                return "." . strval($match[1]);
                        }, $val);
                }
                if (strrpos($val, '|') !== false)
                {
                        $moddb = explode('|', $val);
                        $val = array_shift($moddb);
                }
                if (empty($val))
                {
                        return '';
                }
                if (strpos($val, '[$') !== false)
                {
                        $val = '$' . $val;
                        $val = preg_replace_callback("/\.([^\.\[\]\$]+)/i", "self::callback_preg_var_dian", $val);
                        $p = preg_replace_callback("/\\$([\w]+)/is", "self::callback_make_var", $val);
                        //var_dump($p);exit;
//                        $all = explode('.$', $val);
//                        foreach ($all AS $key => $val)
//                        {
//                                $all[$key] = $key == 0 ? $this->make_var($val) : '[' . $this->make_var($val) . ']';
//                        }
//                        $p = implode('', $all);
                }
                else
                {
                        $p = $this->make_var($val);
                }

                if (!empty($moddb))
                {
                        foreach ($moddb AS $key => $mod)
                        {
                                $s = explode(':', $mod);
                                switch ($s[0])
                                {
                                        case 'escape':
                                                $s[1] = trim($s[1], '"');
                                                if ($s[1] == 'html')
                                                {
                                                        $p = 'htmlspecialchars(' . $p . ')';
                                                }
                                                elseif ($s[1] == 'url')
                                                {
                                                        $p = 'urlencode(' . $p . ')';
                                                }
                                                elseif ($s[1] == 'decode_url')
                                                {
                                                        $p = 'urldecode(' . $p . ')';
                                                }
                                                elseif ($s[1] == 'quotes')
                                                {
                                                        $p = 'addslashes(' . $p . ')';
                                                }
                                                elseif ($s[1] == 'u8_url')
                                                {
                                                        if (EC_CHARSET != 'utf-8')
                                                        {
                                                                $p = 'urlencode(ecs_iconv("' . EC_CHARSET . '", "utf-8",' . $p . '))';
                                                        }
                                                        else
                                                        {
                                                                $p = 'urlencode(' . $p . ')';
                                                        }
                                                }
                                                elseif ($s[1] == 'js_str')
                                                {
                                                        $p = 'addslashes(str_replace(array("\r","\n")," ",' . $p . '))';
                                                }
                                                else
                                                {
                                                        $p = 'htmlspecialchars(' . $p . ')';
                                                }
                                                break;

                                        case 'nl2br':
                                                $p = 'nl2br(' . $p . ')';
                                                break;

                                        case 'default':
                                                $s[1] = $s[1]{0} == '$' ? $this->get_val(substr($s[1], 1)) : "'$s[1]'";
                                                $p = 'empty(' . $p . ') ? ' . $s[1] . ' : ' . $p;
                                                break;

                                        case 'truncate':
                                                $p = 'sub_str(' . $p . ",$s[1])";
                                                break;

                                        case 'strip_tags':
                                                $p = 'strip_tags(' . $p . ')';
                                                break;
                                        case 'count_characters':
                                                $p = 'strlen(' . $p . ')';
                                                break;
                                        case 'int_add':
                                                $p = 'intval(' . $p . ')+intval(' . $s[1] . ')';
                                                break;
                                        case 'replace':
                                                $s[1] = trim($s[1], '"');
                                                $s[2] = trim($s[2], '"');
                                                $p = 'str_replace(' . $s[1] . ', ' . $s[2] . ',' . $p . ')';
                                                break;
                                        case 'trim':

                                                break;
                                        default:
                                                # code...
                                                break;
                                }
                        }
                }
                return $p;
        }

        /**
         * 正则替换，回调函数
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @param type $matches
         * @return type
         */
        private function callback_select($matches)
        {
                return $this->select($matches[1]);
        }

        /**
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @param type $matchs
         * @return string
         */
        protected function callback_preg_var_dian($matchs)
        {
                $val = trim($matchs[1], '\'"');
                $val = '[\'' . $val . '\']';
                return $val;
        }

        /**
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @param type $matchs
         * @return type
         */
        private function callback_make_var($matchs)
        {
                return $this->make_var($matchs[1]);
        }

        /**
         * 处理去掉$的字符串
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $val
         * @return  bool
         */
        protected function make_var($val)
        {
                if (strrpos($val, '.') === false)
                {
                        if (isset($this->_var[$val]) && isset($this->_patchstack[$val]))
                        {
                                $val = $this->_patchstack[$val];
                        }
                        $p = '$this->_var[\'' . $val . '\']';
                }
                else
                {
                        $t = explode('.', $val);
                        $_var_name = array_shift($t);
                        if (isset($this->_var[$_var_name]) && isset($this->_patchstack[$_var_name]))
                        {
                                $_var_name = $this->_patchstack[$_var_name];
                        }
                        if ($_var_name == 'smarty')
                        {
                                $p = $this->_compile_smarty_ref($t);
                        }
                        else
                        {
                                $p = '$this->_var[\'' . $_var_name . '\']';
                        }
                        foreach ($t AS $val)
                        {
                                //修复bug xtr
                                //$val = str_replace("'", "", $val);
                                $val = trim($val, '\'"');
                                $p.= '[\'' . $val . '\']';
                        }
                }

                return $p;
        }

        /**
         * 判断变量是否被注册并返回值
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $name
         * @return  mix
         */
        public function &get_template_vars($name = null)
        {
                if (empty($name))
                {
                        return $this->_var;
                }
                elseif (!empty($this->_var[$name]))
                {
                        return $this->_var[$name];
                }
                else
                {
                        $_tmp = null;

                        return $_tmp;
                }
        }

        /**
         * 处理if标签
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $tag_args
         * @param   bool       $elseif
         * @return  string
         */
        protected function _compile_if_tag($tag_args, $elseif = false)
        {
                //preg_match_all('/\-?\d+[\.\d]+|\'[^\'|\s]*\'|"[^"|\s]*"|[\$\w\.]+|!==|===|==|!=|<>|<<|>>|<=|>=|&&|\|\||\(|\)|,|\!|\^|=|&|<|>|~|\||\%|\+|\-|\/|\*|\@|\S/', $tag_args, $match);
                //增加支持类似与 {if $cat_desc|count_characters:true > 500} 的标签
                preg_match_all('/\-?\d+[\.\d]+|\'[^\'|\s]*\'|"[^"|\s]*"|[\$\w\.]+(?:\|[\w]+\:[\w]+)?|!==|===|==|!=|<>|<<|>>|<=|>=|&&|\|\||\(|\)|,|\!|\^|=|&|<|>|~|\||\%|\+|\-|\/|\*|\@|\S/', $tag_args, $match);
                $tokens = $match[0];
                // make sure we have balanced parenthesis
                $token_count = array_count_values($tokens);
                if (!empty($token_count['(']) && $token_count['('] != $token_count[')'])
                {
                        // $this->_syntax_error('unbalanced parenthesis in if statement', E_USER_ERROR, __FILE__, __LINE__);
                }
                for ($i = 0, $count = count($tokens); $i < $count; $i++)
                {
                        $token = &$tokens[$i];
                        switch (strtolower($token))
                        {
                                case 'eq':
                                        $token = '==';
                                        break;

                                case 'ne':
                                case 'neq':
                                        $token = '!=';
                                        break;

                                case 'lt':
                                        $token = '<';
                                        break;

                                case 'le':
                                case 'lte':
                                        $token = '<=';
                                        break;

                                case 'gt':
                                        $token = '>';
                                        break;

                                case 'ge':
                                case 'gte':
                                        $token = '>=';
                                        break;

                                case 'and':
                                        $token = '&&';
                                        break;

                                case 'or':
                                        $token = '||';
                                        break;

                                case 'not':
                                        $token = '!';
                                        break;

                                case 'mod':
                                        $token = '%';
                                        break;

                                default:
                                        if ($token[0] == '$')
                                        {
                                                $token = $this->get_val(substr($token, 1));
                                        }
                                        break;
                        }
                }

                if ($elseif)
                {
                        return '<?php elseif (' . implode(' ', $tokens) . '): ?>';
                }
                else
                {
                        return '<?php if (' . implode(' ', $tokens) . '): ?>';
                }
        }

        /**
         * 处理foreach标签
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @access  public
         * @param   string     $tag_args
         * @return  string
         */
        protected function _compile_foreach_start($tag_args)
        {
                $attrs = $this->get_para($tag_args, 0);
                $arg_list = array();
                $from = $attrs['from'];
                if (isset($this->_var[$attrs['item']]) && !isset($this->_patchstack[$attrs['item']]))
                {
                        $this->_patchstack[$attrs['item']] = $attrs['item'] . '_' . str_replace(array(' ', '.'), '_', microtime());
                        $attrs['item'] = $this->_patchstack[$attrs['item']];
                }
                else
                {
                        $this->_patchstack[$attrs['item']] = $attrs['item'];
                }
                $item = $this->get_val($attrs['item']);

                if (!empty($attrs['key']))
                {
                        $key = $attrs['key'];
                        $key_part = $this->get_val($key) . ' => ';
                }
                else
                {
                        $key = null;
                        $key_part = '';
                }

                if (!empty($attrs['name']))
                {
                        $name = $attrs['name'];
                }
                else
                {
                        $name = null;
                }
                // Xiao Tangren ：： 2013-09-10
                // 修复compiled缓存，foreach中单引号的bug。 
                $attrs_key = str_replace("'", "\'", $attrs['key']);
                $attrs_item = str_replace("'", "\'", $attrs['item']);
                $output = '<?php ';
                $output .= "\$_from = {$from}; if (!is_array(\$_from) && !is_object(\$_from)) { settype(\$_from, 'array'); }; \$this->push_vars('$attrs_key', '$attrs_item');";
                if (!empty($name))
                {
                        $foreach_props = "\$this->_foreach['$name']";
                        $output .= "{$foreach_props} = array('total' => count(\$_from), 'iteration' => 0);\n";
                        $output .= "if ({$foreach_props}['total'] > 0):\n";
                        $output .= "    foreach (\$_from AS $key_part$item):\n";
                        $output .= "        {$foreach_props}['iteration']++;\n";
                }
                else
                {
                        $output .= "if (count(\$_from)):\n";
                        $output .= "    foreach (\$_from AS $key_part$item):\n";
                }
                return $output . '?>';
        }

        /**
         * 将 foreach 的 key, item 放入临时数组
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @param  mixed    $key
         * @param  mixed    $val
         * @return  void
         */
        protected function push_vars($key, $val)
        {
                if (!empty($key))
                {
                        array_push($this->_temp_key, "\$this->_vars['$key']='" . $this->_vars[$key] . "';");
                }
                if (!empty($val))
                {
                        array_push($this->_temp_val, "\$this->_vars['$val']='" . $this->_vars[$val] . "';");
                }
        }

        /**
         * 弹出临时数组的最后一个
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @return  void
         */
        protected function pop_vars()
        {
                $key = array_pop($this->_temp_key);
                $val = array_pop($this->_temp_val);
                if (!empty($key))
                {
                        eval($key);
                }
        }

        /**
         * 处理smarty开头的预定义变量
         *
         * @access  public
         * @param   array   $indexes
         *
         * @return  string
         */
        protected function _compile_smarty_ref(&$indexes)
        {
                /* Extract the reference name. */
                $_ref = $indexes[0];

                switch ($_ref)
                {
                        case 'now':
                                $compiled_ref = 'time()';
                                break;

                        case 'foreach':
                                array_shift($indexes);
                                $_var = $indexes[0];
                                $_propname = $indexes[1];
                                switch ($_propname)
                                {
                                        case 'index':
                                                array_shift($indexes);
                                                $compiled_ref = "(\$this->_foreach['$_var']['iteration'] - 1)";
                                                break;

                                        case 'first':
                                                array_shift($indexes);
                                                $compiled_ref = "(\$this->_foreach['$_var']['iteration'] <= 1)";
                                                break;

                                        case 'last':
                                                array_shift($indexes);
                                                $compiled_ref = "(\$this->_foreach['$_var']['iteration'] == \$this->_foreach['$_var']['total'])";
                                                break;

                                        case 'show':
                                                array_shift($indexes);
                                                $compiled_ref = "(\$this->_foreach['$_var']['total'] > 0)";
                                                break;

                                        default:
                                                $compiled_ref = "\$this->_foreach['$_var']";
                                                break;
                                }
                                break;

                        case 'get':
                                $compiled_ref = '$_GET';
                                break;

                        case 'post':
                                $compiled_ref = '$_POST';
                                break;

                        case 'cookies':
                                $compiled_ref = '$_COOKIE';
                                break;

                        case 'env':
                                $compiled_ref = '$_ENV';
                                break;

                        case 'server':
                                $compiled_ref = '$_SERVER';
                                break;

                        case 'request':
                                $compiled_ref = '$_REQUEST';
                                break;

                        case 'session':
                                $compiled_ref = '$_SESSION';
                                break;

                        default:
                                // $this->_syntax_error('$smarty.' . $_ref . ' is an unknown reference', E_USER_ERROR, __FILE__, __LINE__);
                                break;
                }
                array_shift($indexes);

                return $compiled_ref;
        }

        protected function smarty_insert_scripts($args)
        {
                static $scripts = array();
                $arr = explode(',', str_replace(' ', '', $args['files']));
                $str = '';
                foreach ($arr AS $val)
                {
                        if (in_array($val, $scripts) == false)
                        {
                                $scripts[] = $val;
                                if ($val{0} == '.')
                                {
                                        $str .= '<script type="text/javascript" src="' . $val . '"></script>';
                                }
                                else
                                {
                                        $str .= '<script type="text/javascript" src="js/' . $val . '"></script>';
                                }
                        }
                }
                return $str;
        }

        /**
         * 二次编译（动态内容）
         * @author Xiao Tangren  <unphp@qq.com>
         * @data 2014-03-05
         * @param type $name
         * @return type
         */
        protected function insert_mod($name) // 处理动态内容
        {
                list($fun, $para) = explode('|', $name);
                $para = unserialize($para);
                $fun = 'insert_' . $fun;
                return $fun($para);
        }

        protected function str_trim($str)
        {
                /* 处理'a=b c=d k = f '类字符串，返回数组 */
                while (strpos($str, '= ') != 0)
                {
                        $str = str_replace('= ', '=', $str);
                }
                while (strpos($str, ' =') != 0)
                {
                        $str = str_replace(' =', '=', $str);
                }

                return explode(' ', trim($str));
        }

        protected function html_options($arr)
        {
                $selected = $arr['selected'];

                if ($arr['options'])
                {
                        $options = (array) $arr['options'];
                }
                elseif ($arr['output'])
                {
                        if ($arr['values'])
                        {
                                foreach ($arr['output'] AS $key => $val)
                                {
                                        $options["{$arr[values][$key]}"] = $val;
                                }
                        }
                        else
                        {
                                $options = array_values((array) $arr['output']);
                        }
                }
                if ($options)
                {
                        foreach ($options AS $key => $val)
                        {
                                $out .= $key == $selected ? "<option value=\"$key\" selected>$val</option>" : "<option value=\"$key\">$val</option>";
                        }
                }
                return $out;
        }

        protected function html_select_date($arr)
        {
                $pre = $arr['prefix'];
                if (isset($arr['time']))
                {
                        if (intval($arr['time']) > 10000)
                        {
                                $arr['time'] = gmdate('Y-m-d', $arr['time'] + 8 * 3600);
                        }
                        $t = explode('-', $arr['time']);
                        $year = strval($t[0]);
                        $month = strval($t[1]);
                        $day = strval($t[2]);
                }
                $now = gmdate('Y', $this->_nowtime);
                if (isset($arr['start_year']))
                {
                        if (abs($arr['start_year']) == $arr['start_year'])
                        {
                                $startyear = $arr['start_year'];
                        }
                        else
                        {
                                $startyear = $arr['start_year'] + $now;
                        }
                }
                else
                {
                        $startyear = $now - 3;
                }

                if (isset($arr['end_year']))
                {
                        if (strlen(abs($arr['end_year'])) == strlen($arr['end_year']))
                        {
                                $endyear = $arr['end_year'];
                        }
                        else
                        {
                                $endyear = $arr['end_year'] + $now;
                        }
                }
                else
                {
                        $endyear = $now + 3;
                }
                $out = "<select name=\"{$pre}Year\">";
                for ($i = $startyear; $i <= $endyear; $i++)
                {
                        $out .= $i == $year ? "<option value=\"$i\" selected>$i</option>" : "<option value=\"$i\">$i</option>";
                }
                if ($arr['display_months'] != 'false')
                {
                        $out .= "</select>&nbsp;<select name=\"{$pre}Month\">";
                        for ($i = 1; $i <= 12; $i++)
                        {
                                $out .= $i == $month ? "<option value=\"$i\" selected>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>" : "<option value=\"$i\">" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>";
                        }
                }
                if ($arr['display_days'] != 'false')
                {
                        $out .= "</select>&nbsp;<select name=\"{$pre}Day\">";
                        for ($i = 1; $i <= 31; $i++)
                        {
                                $out .= $i == $day ? "<option value=\"$i\" selected>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>" : "<option value=\"$i\">" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>";
                        }
                }
                return $out . '</select>';
        }

        protected function html_radios($arr)
        {
                $name = $arr['name'];
                $checked = $arr['checked'];
                $options = $arr['options'];
                $out = '';
                foreach ($options AS $key => $val)
                {
                        $out .= $key == $checked ? "<input type=\"radio\" name=\"$name\" value=\"$key\" checked>&nbsp;{$val}&nbsp;" : "<input type=\"radio\" name=\"$name\" value=\"$key\">&nbsp;{$val}&nbsp;";
                }
                return $out;
        }

        protected function html_select_time($arr)
        {
                $pre = $arr['prefix'];
                if (isset($arr['time']))
                {
                        $arr['time'] = gmdate('H-i-s', $arr['time'] + 8 * 3600);
                        $t = explode('-', $arr['time']);
                        $hour = strval($t[0]);
                        $minute = strval($t[1]);
                        $second = strval($t[2]);
                }
                $out = '';
                if (!isset($arr['display_hours']))
                {
                        $out .= "<select name=\"{$pre}Hour\">";
                        for ($i = 0; $i <= 23; $i++)
                        {
                                $out .= $i == $hour ? "<option value=\"$i\" selected>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>" : "<option value=\"$i\">" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>";
                        }

                        $out .= "</select>&nbsp;";
                }
                if (!isset($arr['display_minutes']))
                {
                        $out .= "<select name=\"{$pre}Minute\">";
                        for ($i = 0; $i <= 59; $i++)
                        {
                                $out .= $i == $minute ? "<option value=\"$i\" selected>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>" : "<option value=\"$i\">" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>";
                        }

                        $out .= "</select>&nbsp;";
                }
                if (!isset($arr['display_seconds']))
                {
                        $out .= "<select name=\"{$pre}Second\">";
                        for ($i = 0; $i <= 59; $i++)
                        {
                                $out .= $i == $second ? "<option value=\"$i\" selected>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</option>" : "<option value=\"$i\">$i</option>";
                        }

                        $out .= "</select>&nbsp;";
                }
                return $out;
        }

        protected function cycle($arr)
        {
                static $k, $old;

                $value = explode(',', $arr['values']);
                if ($old != $value)
                {
                        $old = $value;
                        $k = 0;
                }
                else
                {
                        $k++;
                        if (!isset($old[$k]))
                        {
                                $k = 0;
                        }
                }
                echo $old[$k];
        }

        protected function make_array($arr)
        {
                $out = '';
                foreach ($arr AS $key => $val)
                {
                        if ($val{0} == '$')
                        {
                                $out .= $out ? ",'$key'=>$val" : "array('$key'=>$val";
                        }
                        else
                        {
                                $out .= $out ? ",'$key'=>'$val'" : "array('$key'=>'$val'";
                        }
                }
                return $out . ')';
        }

        protected function smarty_create_pages($params)
        {
                extract($params);
                if (empty($page))
                {
                        $page = 1;
                }
                if (!empty($count))
                {
                        $str = "<option value='1'>1</option>";
                        $min = min($count - 1, $page + 3);
                        for ($i = $page - 3; $i <= $min; $i++)
                        {
                                if ($i < 2)
                                {
                                        continue;
                                }
                                $str .= "<option value='$i'";
                                $str .= $page == $i ? " selected='true'" : '';
                                $str .= ">$i</option>";
                        }
                        if ($count > 1)
                        {
                                $str .= "<option value='$count'";
                                $str .= $page == $count ? " selected='true'" : '';
                                $str .= ">$count</option>";
                        }
                }
                else
                {
                        $str = '';
                }
                return $str;
        }

        protected function autoMkdir($file)
        {
                $pathinfo = pathinfo($file);
                if (!empty($pathinfo['dirname']))
                {
                        if (file_exists($pathinfo['dirname']) === false)
                        {
                                if (@mkdir($pathinfo['dirname'], 0777, true) === false)
                                {
                                        return false;
                                }
                                chmod($pathinfo['dirname'], 0777);
                        }
                }
        }

        protected function getCachefilepath($filename, $cache_id)
        {
                $pathinfo = pathinfo($filename);
                $dir = str_replace($pathinfo['basename'], '', $filename);
                $filename = str_replace(DIRECTORY_SEPARATOR, '_', $filename);
                $cachename = basename($filename, strrchr($filename, '.')) . $cache_id;
                $hash_dir = $this->cache_dir . $dir . substr(md5($cachename), 0, 2) . '/' . substr(md5($cachename), 3, 2);
                $cachefile = $hash_dir . '/' . $cachename . '.php';
                return $cachefile;
        }

        protected function doCreateCache($filename, $cache_id, $out)
        {
                $cachefile = $this->getCachefilepath($filename, $cache_id);
                $data = serialize(
                        array(
                            'template' => $this->template,
                            'expires' => $this->_nowtime + $this->cache_lifetime,
                            'maketime' => $this->_nowtime
                        )
                );
                while (strpos($out, "\n\n") !== false)
                {
                        $out = str_replace("\n\n", "\n", $out);
                }
                $this->autoMkdir($cachefile);
                if (file_put_contents($cachefile, '<?php exit;?>' . $data . $out, LOCK_EX) === false)
                {
                        trigger_error('can\'t write:' . $cachefile);
                }
                return $out;
        }
        
        protected function escapeTag($tag){
                $escapeList = array('{','}');
                foreach ($escapeList as $value)
                {
                        $tag = str_replace($value, '\\'.$value, $tag);
                }
                return $tag;
        }

}
