UnPHP
=====

UnPHP is a framework of PHP.

==============================
UnPHP框架 目录结构说明：

	|-- UnPHP
		|-- Core         目录 (框架核心类)
		|-- Lib          目录 (框架基础类)
		|-- Ext          目录 (框架扩展模块)
		|-- UnPHP.PHP    文件 (框架入口文件)

==============================

==============================

测试项目 目录结构说明：

	|-- test			    项目主目录
		|-- conf                    目录 (配置)
		|    |-- commin.ini         项目配置文件
		|
		|-- library                 目录 (项目公共类库)
		|
		|-- www                     目录 (应用)
		|    |-- controllers        目录 (应用的主“控制器”目录)
		|    |
		|    |-- library            目录 (应用类库)
		|    |
		|    |-- Bootstrap.php      文件 (应用初始引导文件)
		|
		|-- index.php               文件 (项目入口文件)
		|
		|-- rewrite.conf            文件 (项目nginx重写规则，单一入口。)



