��    /      �  C           �       �  
   �     �     �  7   �               +     �     �     �  $   �  '        -     2  .   J     y  
        �     �     �  
   �     �     �     �  &   �     �  0        5  -   :     h  o   n  �   �  1   f	     �	     �	     �	     �	  :   �	     
     
     $
  &   -
     T
     `
  �  h
         �  	   �     �     �  2   �  	          e        �     �  	   �     �  6   �            4         U     [     g     s     �     �     �     �     �     �     �  7   �       -        <  x   C  �   �  <   T     �     �  	   �     �  0   �     �     �  	             +  	   8                   +              (             '      %   -           *          .                   )                 #   /       ,          	                "   &                 !                             $       
                     A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX Add CID Lookup Source Add Source CID Lookup Source Cache results Checking for cidlookup field in core's incoming table.. Database Database name Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Delete CID Lookup source ERROR: failed:  Edit Source Enter a description for this source. FATAL: failed to transform old routes:  Host Host name or IP address Migrating channel routing to Zap DID routing.. MySQL MySQL Host MySQL Password MySQL Username None Not Needed Not yet implemented OK Password Password to use in HTTP authentication Path Path of the file to GET<br/>e.g.: /cidlookup.php Port Port HTTP server is listening at (default 80) Query Query string, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: number=[NUMBER]&source=crm Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Removing deprecated channel field from incoming.. Source Source Description Source type Source: %s (id %s) Sources can be added in Caller Name Lookup Sources section Submit Changes SugarCRM Username Username to use in HTTP authentication not present removed Project-Id-Version: IssabelPBX 2.5 Chinese Translation
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2015-05-05 21:35-0400
PO-Revision-Date: 2011-04-14 00:00+0800
Last-Translator: 周征晟 <zhougongjizhe@163.com>
Language-Team: EdwardBadBoy <zhougongjizhe@163.com>
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Poedit-Language: Chinese
X-Poedit-Country: CHINA
X-Poedit-SourceCharset: utf-8
 查找源是你指定的用来解析入局的数字主叫ID的源，你可以把一条入局线路与特定的CID源链接起来。在这种工作方式下，你将获得更详细的CDR报告，报告中将包含直接从你的CRM里获取的内容。你也可以安装电话簿模块以提供简易的数字<->名字关联。请注意，名字查找将会减慢你的PBX服务器。 添加CID查找源 添加源 CID查找源 缓存结果 正在检查incoming表的cidlookup字段。。。 数据库 数据库名 设置是否将查询结果缓存到astDB；它将覆盖当前设置。它不影响内部源的行为 删除CID查找源 错误：失败： 编辑源 为此源添加描述。 致命错误：无法转换旧的路由（线路）： 主机 主机名或者IP地址 正在将频道路由迁移到Zap DID路由。。。 MySQL MySQL主机 MySQL密码 MySQL用户名 无 没有必要 尚未实现 完成 密码 用于HTTP鉴权的密码 路径 要请求的文件的路径<br/>例如：/cidlookup.php 端口 HTTP服务器监听的端口（默认为80） 查询 设置查询字符串，特殊标识符“[NUMBER]”会被替换成主叫号码<br/>例如：number=[NUMBER]&source=crm 设置查询字符串，特殊标识符“[NUMBER]”会被替换成主叫号码<br/>例如：SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' 正在从incoming表中移除废弃的channel字段。。。 源 源的描述 源类型 源：%s (id %s) 可以向呼叫者姓名查找源小节添加源 提交更改 SugarCRM 用户名 用于HTTP鉴权的用户名 没有出现 已移除 