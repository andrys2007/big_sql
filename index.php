<? 
/*
КОММЕНТАРИЙ К ЗАДАНИЮ:

В задании указано две таблицы, но в таблице product есть catgories ManyToMany -многие ко многим 
что подрозумевает наличие 3 -связывающей таблицы + в products.json идут  данные "categoryEId": [2,3]
что также говорит о том, что есть 3 таблица и она была сделана:


CREATE TABLE `link` (
  `link_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  UNIQUE KEY `id` (`link_id`),
  KEY `a1` (`category_id`,`product_id`) USING BTREE,
  KEY `category_id` (`category_id`),
  KEY `a2` (`product_id`),
  CONSTRAINT `a2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `a1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9244004 DEFAULT CHARSET=utf8



Также по заданию не совсем ясны поля eId ...В теории к тем же category поля идут:
  id (int)     <-  должно быть ключевым
  title (string length min: 3, max 12)
  eId (int|null)
  
где ключевым eId - null  не должно быть , поэтому Category.id и Product.id как основные т.е.
в них сажаются  из "eId": 1... Опять таки в задании не совсем понятно это.

По загрузке данных.

Сажаем данные пачкой. Т.е. сначал сажаем  Category, так как данных не мого то  тут просто все insert -insert-update ,а
потом сажаем product и link.

Тут некоторые ньюансы.

!Так как таблица link  свзяующая база с внешними ключами, то она должна грузится последней , так как на момент записи (link) ,
product может еще не быть в таблице и выйдет ошибка связки, поэтому таблица link грузится во временную базу а уже после всех действий, 
коипруется одним заходом в основную базу. 

Так как product по полям шире и информации больше скидывается чем link(в link всего два поля), 
то кол-во строк для product (values) будет меньше чем link для скидки , поэтому делаем два константы,
где каждая константа говорит через сколько строк делать скидку в таблицу:
  
  MAX_ROW_APPEND_PROD 
  MAX_ROW_APPEND_LINK
  

Это дает нам более тонкую-оптимальную настройку для сброса данных.

*/

define('DATABASE',"big");
define('HOST_DB',"localhost");
define('LOGIN_DB',"sysdba");
define('PASWORD_DB',"19721101");


/*
  MAX_CATEGORY слишком много не заложено (сколько память позволит)
  Уж если я products.json делаю любой то и этот можно было аналогично сделать
*/

/************** ДЛЯ ГЕНЕРАЦИИ ТЕСТОВЫХ ФАЙЛОВ **************************/
define('MAX_CATEGORY',1);   //  Для category.json 100
define('MAX_PRODUCT',5);    //  Для products.json 30000
define('GEN_FILE',false);    //  GEN_FILE = true , то сделает 2 файла в текущей папке и сотрет текущие , false запустит на выполненин  скрипт

define('MAX_ROW_APPEND_PROD',1000);  // по сколько записей кидаем за раз (примерно) для Product  (кол.строк)
define('MAX_ROW_APPEND_LINK',3000);  // по сколько записей кидаем за раз (примерно)  для Link   (кол.строк) 


/**********************************************************************************/
/*************** Вспомогательный класс - для удобства работы с SQL  ***************/
/**********************************************************************************/
class DBC
{
   static $DB=NULL;
   static $REZULT=NULL;
   static $COUNT=0;
   
   static public function Connect()
   {
      self::$DB=mysqli_connect(HOST_DB, LOGIN_DB, PASWORD_DB,DATABASE);
      if ( mysqli_connect_errno() ) 
	     {
           echo "Не удалось подключиться:".mysqli_connect_error();
           die();
         }
      mysqli_query(self::$DB,"SET NAMES utf8");
   }
   
   static public function Append($SQL)
    {
 	  if ( !mysqli_multi_query(self::$DB,$SQL) )
	     {
		   echo "Error: ".mysqli_error(self::$DB)."<BR><BR>";
		   echo $SQL."<BR><BR>";		  
	     }
	}   	

   static public function Select($SQL)
    {
	  self::$REZULT=mysqli_query(self::$DB,$SQL);
      self::$COUNT=mysqli_num_rows(self::$REZULT);
      if  (self::$COUNT>0) mysqli_data_seek(self::$REZULT,0);
	}
	
   static public function Delete($SQL)
    {
       if ( !mysqli_query(self::$DB,$SQL) )
         {
		   echo "Error: ".mysqli_error(self::$DB)."<BR><BR>";
		   echo $SQL."<BR><BR>";		  
		   self::$COUNT=0;
      	   return 0;
         }  
      self::$COUNT=1;
   }    	
   
   static public function Close()
    {
       mysqli_close(self::$DB);   		
	}
}

/******** ГЕНЕРАТОР ФАЙЛОВ JSON ДЛЯ ТЕСТА ********/
function CreateTestFiles()
{
   $start=true;
   $handler = fopen("products.json", "w");
   fputs($handler, "[");

   $k=1;
   $Category=array();
   for($i=1;$i<=MAX_CATEGORY;$i++)
    {
       $Category[]=array("eId"=>$i,"title"=>"Category ".$i);
       for($j=1;$j<=MAX_PRODUCT;$j++)
        {
	      $RandSizeArray=rand(1,4);
	      $ListCat=array();
	      for($m=0;$m<$RandSizeArray;$m++)
	        {
			  $value=round(rand(1,MAX_CATEGORY),0);	
			  
			  $found=false;
			  for($t=0;$t<count($ListCat);$t++)
			     if ($value==$ListCat[$t]) { $found=true; break; }
			  if (!$found)  $ListCat[]=$value; 
			}
		   	
	      $Product=array("eId"=>$k,"title"=>"Product ".$k,"price"=>rand(100,10000),"categoriesEId"=>$ListCat); 
		  
		  if ($i==MAX_CATEGORY && $j==MAX_PRODUCT) fputs($handler, json_encode($Product));
		  else fputs($handler, json_encode($Product).",");
	  
	      $k++;
     	  unset($ListCat);
        }
    }

    fputs($handler, "]");
    fclose($handler);

    file_put_contents("categories.json",json_encode($Category));
}



/***************************************************************************************************************
  JFILE вспомогательный класс для поблочного insert
***************************************************************************************************************/
class JFILE
{
   static $ProductValues=array();
   static $LinkValues=array();
	
   /*
     GetDataFromFile 
	 
     Так как больщой файл невозможно открыть целиком , то читаем посегментно.
     Файл примерно делим на 3 части и считываем. 
     Считывание работает быстро.
  
     PackData - функция для обаботки очередногй порции JSON массива. В нее передаются параметры:
        - данные в видео JSON
        - $Last и признак что последний блок для (сброса остатоков valeus)
   */	
   static public function GetDataFromFile($NameFile)  
    {
      self::$ProductValues=array();  // Для накопления по продукту
      self::$LinkValues=array();     // Для связываюшей
	  
      $FileSize=filesize($NameFile);	
      $f = fopen($NameFile, 'r');
      if ( $f<0 )
         {
		   echo 'Error open file<BR>';
		   return ;
	     } 
  	 
      fseek($f,1);	  // пропускаем квадартную скобу
      $CountRead=round($FileSize/3,0);	 
	  
	 // echo"***************************START********************************<BR>";
	   
      $text_ost="";
	  $ReadSize=0;
      while( !feof($f) )	 
       {  
	      $text=$text_ost.fread($f,$CountRead);
		  $ReadSize+=$CountRead;
		  
    	  if ( $ReadSize>=$FileSize )   // Надо ИМЕННО ТАК, что бы убрать квадратную скобку
		     {                          
				$text=substr($text,0,strlen($text)-1); // убираем закрывающую квадратную скобку
				break;
			 }
			
	     if ( strlen($text)>0 )
	        {
		      $pos=strrpos($text,'},{');
		      $ForJSON=substr($text,0,$pos+1);
		      $text_ost=substr($text,$pos+2,strlen($text)-($pos+2));
			  
		      self::PackData('['.$ForJSON.']',false);
  		   }
		   
       }
      fclose($f);	
      self::PackData('['.$text.']',true);
   }
   
   // Функция проверяет не пора ли скидывать данные tmptb/link
   static public function CheckSendLink($End=false)
    {
	   $Count=count(self::$LinkValues);	
	   if ( $Count>=MAX_ROW_APPEND_LINK || ( $End  && $Count>0 ) )	
	      {
            $mmm=implode(",",self::$LinkValues);	
  		    DBC::Append("insert ignore into tmptb (product_id,category_id) values ".$mmm);
  		    self::$LinkValues=array();
	  	  }
	 }

   // Функция проверяет не пора ли скидывать данные product
   static public function CheckSendProduct($End=false)
    {
	  $Count=count(self::$ProductValues);	
      if ( $Count>=MAX_ROW_APPEND_PROD || ( $End && $Count>0 ) )
	     {
           $mmm=implode(",",self::$ProductValues);	
  	       DBC::Append("insert into product (id,price,eid) values ".$mmm." on duplicate key update price = values(price), eid=values(eid)");
		   self::$ProductValues=array();
	     }
	}

   // Вынимаем из очередной порции JSON массива 
   static public function PackData($JSonString,$Last)
    {
	  //echo $JSonString."<BR>";
	  
      $prodlist=json_decode($JSonString,true);	
	  
	  
	  if ( !is_null($prodlist) )
  	     {
   	       $EndElement=end($prodlist);
   	       reset($prodlist);
   	       $LastElement=false;
	  
   	       foreach ($prodlist as $product)
            {
	          $katlist=$product["categoriesEId"];
			  
   	          for($j=0;$j<count($katlist);$j++)
                  self::$LinkValues[]="(".$product["eId"].",".$katlist[$j].')';
			 
              self::$ProductValues[]="(".$product["eId"].",".$product["price"].",".$product["eId"].')';
 
 	     	  self::CheckSendProduct(); // проверка надо ли скидывать или еще копим для product
		      self::CheckSendLink();    // проверка надо ли скидывать или еще копим для link
    	    }
  	     }
	  else echo "ERROR!!! JSonString=>".$JSonString."<BR>";
	  
	  if ( $Last )	// Если последняя итерация то скинуть все что есть
	     {
 		   self::CheckSendProduct(true); 
		   self::CheckSendLink(true);  
		 }
	}
	
}


/************************************************************************************/
/*********************************** ВЫПОЛНЕНИЕ *************************************/
/************************************************************************************/

/* Для генерации тестовыъ файлов */
if ( GEN_FILE )
   {
     CreateTestFiles();
	 echo 'Готово!';
	 return ;
   }

//$start_time=microtime(1);

$categ=json_decode(file_get_contents("categories.json",true),true);

/* собираем запросы для category */
$values=array();
foreach ($categ as $key)
  $values[]="(".$key["eId"].",'".$key["title"]."',".$key["eId"].')';
$values=implode(",",$values);
	  
DBC::Connect();
DBC::Delete("delete from product");
DBC::Delete("delete from category");
DBC::Append("insert into category (id,title,eid) values ".$values." on duplicate key update title = values(title)");


/******** Создаем временную таблицу для сбора связующих ключей ******/
DBC::Append("create temporary table `tmptb` like `link`");			
JFILE::GetDataFromFile("products.json");
DBC::Append("insert into link (category_id, product_id ) select category_id, product_id  from tmptb");			
DBC::Close();

//$start_time=microtime(1)-$start_time;
//echo "Время выполнения  скрипта: ".$start_time."<BR>";
