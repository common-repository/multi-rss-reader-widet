<?php
/*
Plugin Name: Multi-RSS Reader
Version: 0.8.2
Description: show  rss articles sorted by date from some rss url.
Author: @TKI
Author URI: http://edutainment-fun.com/
Plugin URI: http://edutainment-fun.com/hidemaru/wordpress/

  history:
  0.7 2013/08/16
             category / authorに実験的対応
             署名追加
             image に対応
             PR除外
             日付タイプ{date_gap}追加
             description内のタグ除外
             RSSアイコン追加
             多言語対応
             bug-fix jp->ja
  0.7.5
             記事重複チェク
             rss重複チェック
  0.8
             3type layout set 追加.
  0.8.1
             Cache bug 修正
             bug fix.
             tuning.

  0.8.2
             bug fix.

複数のRSSをまとめてタイムソートして表示するウィジェットプラグイン
  
 */

class multi_rss_reader extends WP_Widget {

  //global $flag = 0;
  var $my_error_msg = array('','','','','','','');
  var $option_prefix = 'multi_rss_reader';

  function __construct() {

    load_plugin_textdomain( 'multi_rss_reader', false, basename( dirname( __FILE__ ) ) . '/languages' );

    //ウィジェットの初期設定
    $label = __('Show in time order RSS headlines multiple readers', 'multi_rss_reader');

    $widget_ops = array('description' => $label);
    parent::WP_Widget(false, $name = 'Multi-RSS Reader',$widget_ops);

    if ( ! is_admin() ){
      return;
    }


    // プラグインが有効化されたときに実行されるメソッドを登録
    if (function_exists('register_activation_hook'))
    {
      register_activation_hook(__FILE__, array(&$this, 'activation_hook'));
    }

    // プラグインが停止されたときに実行されるメソッドを登録
    /*if (function_exists('register_deactivation_hook'))
        {
            register_deactivation_hook(__FILE__, array(&$this, 'deactivation_hook'));
        }*/

    // プラグインがアンインストールされたときに実行されるメソッドを登録
    if (function_exists('register_uninstall_hook'))
    {
      register_uninstall_hook(__FILE__, array(&$this, 'uninstall_hook'));
    }

    add_action('admin_menu', array(&$this, 'set_admin_menu_hook'));
  }

  public function activation_hook()
  {
    // 初回有効時のみ、初期化処理を行いたい場合は、オプション値にフラグをセットするなどすればよい
    if (! get_option($this->option_prefix.'items_format' ))
    {
      // オプション値の登録など・・・
      $this->set_default_option_value();
    }
  }

  /*
    public function deactivation_hook()
    {
        // プラグインが停止されたときの処理・・・
    }
   */

  /**
   * unisntall.phpがある場合、unisntall.phpが優先的に実行されるので注意
   */
  public function uninstall_hook()
  {
    // オプション値の削除など・・・
    $this->delete_options();

    // インストール済みフラグを削除する
  }

  //前半: 管理設定関連 / 後半：ウィジェット関連
  function set_admin_menu_hook() {
    // サイドバーのトップ
    //add_menu_page();

    // サイドバーの設定のサブ
    add_options_page( 'Multi RSS Reader',  'Multi RSS Reader'
                      , 'manage_options'  // user
                      , 'multi_rss_reader' // url page
                      , array($this, 'admin_setting_page')); // fook func
  }

  function admin_setting_page() {
    if ( !current_user_can( 'manage_options' ) )  {
      //You do not have sufficient permissions to access this page.
      wp_die( __( 'You do not have sufficient permissions to access this page.', 'multi_rss_reader' ) );
    }
    $this->my_error_msg =  array_pad(array(), 8, '');

    if (isset($_POST['default']) && check_admin_referer('multi-rss-reader-options')){
      $this->set_default_option_value();
    } else{
      if (isset($_POST['items_format']) && check_admin_referer('multi-rss-reader-options')){
        $temp = stripslashes($_POST['items_format']);//strip_tags()
        if (!$temp || strpos($temp,"{lists}") === false) {
          $this->my_error_msg[1] = '<span style="color:#ff0000;">'.__('There is no {lists}.','multi_rss_reader').'</span>';
        }else{
          update_option($this->option_prefix.'items_format' , $temp);
        }
      }

      $this->check_input_layoutformat('item_format_titlelayout',2);
      $this->check_input_layoutformat('item_format',3);
      $this->check_input_layoutformat('item_format_cardlayout',4);

      if (isset($_POST['remove_title_pattern']) && check_admin_referer('multi-rss-reader-options')){
        $temp = stripslashes ($_POST['remove_title_pattern']);
          update_option($this->option_prefix.'remove_title_pattern' , $temp);
      }
    }

    $items_format = get_option($this->option_prefix.'items_format');
    $item_format = get_option($this->option_prefix.'item_format');
    $remove_title_pattern = get_option($this->option_prefix.'remove_title_pattern');

    // print
    echo "<div class='wrap'>";
    screen_icon('options-general');    //screen_icon('edit');
    echo '<h2>' .__( 'Settings', 'multi_rss_reader' ) . '</h2>';

    $this->print_error_msg($this->my_error_msg[0],"<br>");
    echo '<p style="font-size:x-small;"><font color="#555555">※'.__( 'Please set each settings at the appearance page of widget.', 'multi_rss_reader' ).'</font></p>';

    $action = "if( !confirm('".__( 'Do you back to the input before the entire entry?', 'multi_rss_reader' )."') ) { return false; }";
    echo '<form method="post" action="" class="form-table" onreset="'.$action.'">';
    wp_nonce_field('multi-rss-reader-options');
    echo '<table><tr><td valign="top">';
    echo '<table class="form-table">';
    echo '<tr valign="top">
              <td><b>'.__( 'The entire list format', 'multi_rss_reader' ).':</b><br>
                 '.__( '{lists} is replaced  by multiple rss articles through the below template.', 'multi_rss_reader' ).'';
    $this->print_error_msg($this->my_error_msg[1],"<br>");

    $pip = plugin_dir_url(__FILE__) . "image/";
    echo '</td><td><textarea class="widefat" id="" name="items_format" rows="5" cols="40" style="height:90px;">'
        .$items_format.//esc_textarea()
            '</textarea></td></tr>';

    echo '<tr><td><div id="tabs-1-label"><b><img src="'.$pip.'title_line.png">'
        .__( 'each article format(title)', 'multi_rss_reader' ).
        ':</b>';
    $this->print_error_msg($this->my_error_msg[2],"<br>");
    echo '<br><span style="font-size:xx-small;">'
        .__( 'You can use these.{title}{link}{date}', 'multi_rss_reader' ).'{index}{date_gap}{sitetitle}{description}{img}</font></td>
               <td><textarea class="widefat"  id="tabs-1-textarea" name="item_format_titlelayout" style="height:90px;">'
        .get_option($this->option_prefix.'item_format_titlelayout').
            '</textarea></td></tr>';

    echo '<tr><td><div id="tabs-2-label"><b><img src="'.$pip.'magazinelayout.png">'
        .__( 'each article format', 'multi_rss_reader' ).':</b><br>'
            .__( 'The format of every single RSS article.', 'multi_rss_reader' ).'';
    $this->print_error_msg($this->my_error_msg[3],"<br>");
    echo '<br><span style="font-size:xx-small;">'.__( 'You can use these.{title}{link}{date}', 'multi_rss_reader' ).'{index}{date_gap}{sitetitle}{description}{img}</font></td>
               <td><textarea class="widefat" id="tabs-2-textarea" name="item_format" rows="8" cols="40" style="height:120px;">'
        .$item_format.
            '</textarea></td></tr>';

    echo '<tr><td><div id="tabs-3-label"><b><img src="'.$pip.'cardlayout.png">'
        .__( 'each article format(card)', 'multi_rss_reader' ).':</b>';
    $this->print_error_msg($this->my_error_msg[4],"<br>");
    echo '<br><span style="font-size:xx-small;">'.__( 'You can use these.{title}{link}{date}', 'multi_rss_reader' ).'{index}{date_gap}{sitetitle}{description}{img}</font></td>
               <td><textarea class="widefat"  id="tabs-3-textarea" name="item_format_cardlayout" style="height:150px;">'
        .get_option($this->option_prefix.'item_format_cardlayout').
            '</textarea></td></tr>';

    echo '<tr><td><label idf="remove_title_pattern">'.__( 'Article Title Exclude REpattern', 'multi_rss_reader' )
        .':</label></td><td>'
        .'<input type="text" class="widefat" id="remove_title_pattern" name="remove_title_pattern" style="width:280px;" value="'.esc_attr($remove_title_pattern).'"/>'
            .'</td></tr>';

    echo '<tr><td>';    submit_button( __('save', 'multi_rss_reader') );
    echo '</td><td>';
    $action = "if( !confirm('".__('Do you return to initial value of all entry?','multi_rss_reader')."') ) { return false; }";
    echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" value="'.__('initialization','multi_rss_reader').'" name="default" onclick="'.$action.'">';
    echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="reset" value="'.__('reset','multi_rss_reader').'" name="reset">';
    echo '</td></tr></table>';

    //print sample(right side)
    echo '</td><td style="border-color:#000; border-style:solid;background-color:#c5efff;" valign="top" width="270pt">';
    echo 'rss list sample : <br>';

      $this->print_layout(array());
      echo "<div id='tabs-1'>";
      //echo "tabs-1";
      $this->print_sample(get_option($this->option_prefix.'item_format_titlelayout'), $items_format);
      echo "</div>";
      echo "<div id='tabs-2'>";
      //echo "tabs-2";
      $this->print_sample( $item_format, $items_format);
      echo "</div>";
      echo "<div id='tabs-3'>";
      //echo "tabs-3";
      $this->print_sample(get_option($this->option_prefix.'item_format_cardlayout') , $items_format);
      echo "</div>";
      
    echo '</td></tr></table>';
    echo '</form>';
    echo '</div>';
      $this->put_layout_change_script();
  }

    function print_sample($temp, $items_format){
        //echo "test1." . $temp;
      $temp = str_replace("{date}", date('y/m/d H:i'),$temp);
      $temp = str_replace("{sitetitle}", "Multi RSS",$temp);
      $temp = str_replace("{link}", get_option('url'),$temp);
      $temp = str_replace("{category}", "wp-category",$temp);//experimental
      $temp = str_replace("{author}", "name",$temp);//experimental

      $temp = str_replace("{img_width}", "100",$temp);//not workig
      $temp = str_replace("{img_height}", "120",$temp);//not workig
      $temp = str_replace("{description}", "description The quick brown fox jumps over the lazy dog.",$temp);

    $befl = "";//__('ago','multi_rss_reader');
    $minl = "m";//__('minutes','multi_rss_reader');
    $houl = "h";//__('hours','multi_rss_reader');
    $dayl = "d";//__('days','multi_rss_reader');

    $temp1 = str_replace("{index}", "1",$temp);
    $temp1 = str_replace("{title}", "first title --------  ",$temp1);
    $temp1 = str_replace("{date_gap}", '42'.$minl.$befl,$temp1);
    $temp1 = str_replace("{img}", "http://s.wordpress.org/style/images/wp-header-logo.png",$temp1);

    $temp2 = str_replace("{index}", "2",$temp);
    $temp2 = str_replace("{title}", "second title --------  ",$temp2);
    $temp2 = str_replace("{date_gap}", '2'.$houl.$befl,$temp2);
    $temp2 = str_replace("{img}", "http://s.wordpress.org/style/images/wp-header-logo.png",$temp2);

    $temp3 = str_replace("{index}", "3",$temp);
    $temp3 = str_replace("{title}", "third title --------  ",$temp3);
    $temp3 = str_replace("{date_gap}", '14'.$dayl.$befl,$temp3);
    $temp3 = preg_replace("/<[^>]+{img}[^>]+><\/[^>]+>/i","" , $temp3);

    $temp4 = '';
    //if ($temp.length < 60){
      $temp4 = str_replace("{index}", "next No",$temp);
      $temp4 = str_replace("{title}", "next title --------  ",$temp4);
      $temp4 = str_replace("{date_gap}", 'time gap',$temp4);
      $temp4 = preg_replace("/<[^>]+{img}[^>]+><\/[^>]+>/i","" , $temp4);
    //}

    $temp = $temp1 . $temp2 . $temp3
        . $temp4. $temp4. $temp4. $temp4. $temp4. $temp4 ."...";
    $temp =str_replace("{lists}", $temp , $items_format);

    echo $temp;

  }

  // 後半：ウィジェット関連
  //-----[widget]-----------------------------------
  //管理画面にフォームを表示する処理
  function form($instance) {
    // 設定が保存されていない場合はデフォルト値を設定
    $this->set_default_widget_value($instance);

    echo"<table><tr><td width='60%'>"
        ."<font color='#ff0000'>". $this->my_error_msg[0]."</font>"
            ."</td><td width='40%'></td></tr>";
    $this->print_form_input(__('title','multi_rss_reader').':','title',$instance
                            ,"","style='width:100px;'", 15,$this->my_error_msg[1]);
    $this->print_form_input(__('number of topics to be displayed','multi_rss_reader').':','number',$instance
                            ,"(1-30)","style='width:60px;'", 3,$this->my_error_msg[2]);
    $this->print_form_input(__('cache time(min)','multi_rss_reader').':','cachetime',$instance
                            ,"(0-1440)", "style='width:60px;'" , 5,$this->my_error_msg[3]);
    $this->print_form_input(__('Number of characters per an article','multi_rss_reader').':','size',$instance
                            ,"(10-3000)", "style='width:60px;'" , 5,$this->my_error_msg[4]);
    $this->print_form_input(__('Trailing chars of the article when cutting','multi_rss_reader').':','morestr',$instance
                            ,"", "style='width:100px;'" , 8,$this->my_error_msg[5]);

    $this->print_form_input(__('date format','multi_rss_reader').':','dateformat',$instance
                            ,"<a href='http://www.php.net/manual/ja/function.date.php'>PHP: date - Manual</a>","style='width:100px;'", 12,$this->my_error_msg[6]);

    ?>
    <tr><td>
    layout pattern: 
    <?php $this->print_error_msg($this->my_error_msg[7],"<br>"); ?>
    </td><td>
    <?php
    $this->print_layout($instance);
    echo '</td></tr>';
      
    echo "<tr><td colspan=2>";
    _e('RSS URL: (http://hoge.co.jp/rss.xml)') ;
    echo "(0-30)";
    $this->print_error_msg($this->my_error_msg[8],"<br>");
    $j=0;
    for ($i =1 ; $i <= 30 ; $i++){
      if (!($instance['RssUrl'.$i] === '' || empty($instance['RssUrl'.$i]))
          ){
        $this->print_form_input('',"RssUrl".$i,$instance,"","");
      }else {
        //余白 +1   or 最低５つ表示
        $j++;
        if ($j  < 2 || $i <= 5){
          $this->print_form_input('',"RssUrl".$i,$instance,"","");
        }
      }
    }
    echo "</td></tr>";
    echo "</table>";
  }

  function update($new_instance, $old_instance) {
    $this->my_error_msg = array_pad(array(), 9, '');

    // processes widget options to be saved
    unset($instance);
    $instance = $new_instance;

    //入力値のチェック
    $instance['title'] = strip_tags($new_instance['title']);
    if (!$instance['title']) {
      // タイトルが空白の場合、元の値に戻してエラー表示
      $this->my_error_msg[1] = '<span style="color:#ff0000;">'.__('Title is blank. Return to its initial value.','multi_rss_reader').'</span>';
      $instance['title'] = strip_tags($old_instance['title']);
    }

    $outrangel= __('value is out of range','multi_rss_reader');
    if (!$this->check_num_range($instance['number'] , 1 , 30 , 30)){
      $this->my_error_msg[2] = '<span style="color:#ff0000;">'.$outrangel.'</span>';
    }
    if (!$this->check_num_range($instance['cachetime'] , 0 , 1440 , 120)){
      $this->my_error_msg[3] = '<span style="color:#ff0000;">'.$outrangel.'</span>';
    }
    if (!$this->check_num_range($instance['size'] , 10 , 3000 , 200)){
      $this->my_error_msg[4] = '<span style="color:#ff0000;">'.$outrangel.'</span>';
    }

    if (!$instance['dateformat']) {
      $this->my_error_msg[6] = '<span style="color:#ff0000;">'.__('It is blank. Return to its initial value.','multi_rss_reader').'</span>';
      $instance['dateformat'] = strip_tags($old_instance['dateformat']);
    }

    //$this->my_error_msg[7] = '<span style="color:#ff0000;">'.$instance['layouttype'];
    if (!$instance['layouttype']) {
      $instance['layouttype'] = 1;
    }

    //split copy from No.1
    if (strpos($instance['RssUrl1'], " ") !== false){
      $temp = explode(" ",$instance['RssUrl1']);
      for ($i = 1 ; $i <= 30 && $i <= count($temp); $i++){
        $instance['RssUrl'.$i] =$temp[$i-1];
      }
    }
    if (strpos($instance['RssUrl1'], ",") !== false){
      $temp = explode(",",$instance['RssUrl1']);
      for ($i = 1 ; $i <= 30 && $i <= count($temp); $i++){
        $instance['RssUrl'.$i] =$temp[$i-1];
      }
    }
    if (strpos($instance['RssUrl1'], "@") !== false){
      $temp = explode("@",$instance['RssUrl1']);
      for ($i = 1 ; $i <= 30 && $i <= count($temp); $i++){
        $instance['RssUrl'.$i] =$temp[$i-1];
      }
    }
    //echo ($instance['RssUrl5'] ="". $instance['RssUrl1'].":" . strpos($instance['RssUrl1'], " ") . "/" .  count($temp) ."/");

    for ($i = 1 ; $i <= 30 ; $i ++){
      if($instance['RssUrl'.$i]){
        $flg = false;
        for ($j = 1 ; $j < $i ; $j++){
          if ($flg = $this->just_fit_str($instance['RssUrl'.$i],$instance['RssUrl'.$j])){
            $this->my_error_msg[8] .= 'dup-err(delete):'.$i.' ';
            $instance['RssUrl'.$i] = null;
            break;
          }
        }
        if ($flg)
            continue;

        $header = get_headers($instance['RssUrl'.$i]);
        //$this->my_error_msg[7] .= "rss url($i):". $rss->get_title(). "/".strlen($rss->get_title())."|";
        if (strstr($header[0], '200') ){
          /*$rss = fetch_feed($instance['RssUrl'.$i]);  //取得したいRSS
          if (is_wp_error( $rss ) ){
            $this->my_error_msg[7] .= '<span style="color:#ff0000;">'.$i.sprintf(_n('s th may be wrong.','multi_rss_reader')).'</span>';
          }*/
        }else{
          $this->my_error_msg[8] .= '<span style="color:#ff0000;">'.  'err:'.$i/*sprintf(_n('first rss-url may be wrong.','%1$dth rss-url may be wrong.' ,$i, 'multi_rss_reader'),$i)*/.'  </span>';
        }
      }
    }
    //アップデート内容を返す
    return $instance;
  }

  function widget($args, $instance) {
    //ウィジェット表示部
    extract($args);

    $this->set_default_widget_value($instance);

    $number = $instance['number'];
    $cachetime = $instance['cachetime'];
    $remove_title_pattern =  get_option($this->option_prefix.'remove_title_pattern');
    include_once(ABSPATH . WPINC . '/feed.php');


    //RSS読み出し配列に入力
    $rss_index = 0;
    $allrss_index = 0;
    $index_eachrss = 0;
    $new_th = 0 ; // 32472226800 2999-01-02 00:00:00

    $all_items = array($number*3);
    $dates  = array($number*3);
    $titles  = array($number*3);

    echo $before_widget."\n";
    // タイトルはフィルタ処理
    $title = $instance['title'];
    $title = apply_filters('widget_title', $title);
    echo $before_title.'<img src="'.includes_url().'/images/rss.png">'.$title.$after_title."\n";

    //echo "l:".$instance['layouttype'];
    
    for ($i = 1 ; $i <= 30 ; $i ++){
      if($instance['RssUrl'.$i]){
        //RSSのキャッシュ設定
        add_filter( 'wp_feed_cache_transient_lifetime', create_function('$a', 'return '. ($cachetime * 60).';' ));
        $rss = fetch_feed($instance['RssUrl'.$i]);  //取得したいRSS
        if (!is_wp_error( $rss ) ) { //エラーがなければ

          $maxitems = $rss->get_item_quantity($number);  //取得件数
          $rss_items = $rss->get_items(0, $maxitems);   //指定件数分の配列作成

          $index_eachrss = 0;
          foreach($rss_items as $item){
            $rss_index++;

            //時間情報があったものだけを集める
            if($item->get_date('U') > 0
               //除去タイトル
               && (empty($remove_title_pattern) || !preg_match($remove_title_pattern,$item->get_title()))){
              $all_items[$allrss_index] = (clone $item);
              $titles[$allrss_index] = $rss->get_title();
              //ソート用配列準備
              $dates[$allrss_index] = $item->get_date('U');

              $allrss_index++;
              $index_eachrss++;

              //省エネ1(rss内で 表示件数以上)
              if ($index_eachrss >= $number)
                  break;

              //省エネ2(rss全体ので 表示件数より古い)
              if ($new_th > $item->get_date('U'))
                  break;
            }
          }
          //省エネ( n件以上の時に それより古いものは不要)
          // タイトル除去は↑で対応済み
          // 重複除去の場合、総数は減ることがないので問題ない
          if ($allrss_index >= $number && $index_eachrss >= $number){
            $mtt = $dates[$number];//$dates[count($dates)-1];
            if ($mtt > $new_th)
                $new_th = $mtt;
          }
          echo "<!-- get all index -->";
        }else{
          echo "read_err:".$instance['RssUrl'.$i] ." ";
        }
      }
    }

    if (! get_option($this->option_prefix.'items_format' )
         || get_option($this->option_prefix.'items_format' ) === '')
    {
      // オプション値の登録など・・・
      $this->set_default_option_value();
    }

    if($all_items){
      //ソート
      array_multisort($dates, SORT_DESC,$all_items,$titles);
      //cut
      $all_items = array_slice($all_items, 0, $number);

      $i = 0;
      $inner = "";
      $img = array("","","");

      $befl = "";//__('ago','multi_rss_reader');
      $lasttitle = "@@@";
      $lastlink = "@@@";
      try{
        foreach($all_items as $item) {
          echo "<!-- ★i:$i. -->";

          //重複チェック(ソートされているので、前後１つだけチェック)
          //echo "title(".($this->just_fit_str($lasttitle, $item->get_title()))."):". $lasttitle . "<->" .  $item->get_title()
          //    . ", link(".($this->just_fit_str($lasttitle, $item->get_link()))."):". $lasttitle . "<->" . $item->get_link() ."/";
          if ($this->just_fit_str($lasttitle, $item->get_title())
              && $this->just_fit_str($lastlink, $item->get_link())){
            continue;
          }
          if ($this->just_fit_str($lasttitle, $item->get_title()) && strlen($lasttitle) > 15){
            continue;
          }
          if ($this->just_fit_str($lastlink, $item->get_link()) && strlen($lastlink) > 15){
            continue;
          }

          $type = array('','item_format_titlelayout','item_format','item_format_cardlayout');
          $item_str = get_option($this->option_prefix.($type[$instance['layouttype']]));
          $item_str = str_replace("{title}",($lasttitle = $item->get_title()), $item_str );
          $item_str = str_replace("{link}",esc_url($lastlink = $item->get_link()), $item_str );
          $item_str = str_replace("{sitetitle}",($titles[$i]), $item_str );
          if ($item->get_author()){
            $item_str = str_replace("{author}",($item->get_author()->get_name()), $item_str );
          }else{
            $item_str = str_replace("{author}","", $item_str );
          }
          if ($item->get_category()){
            $item_str = str_replace("{category}",($item->get_category()->get_label()), $item_str );
          }else{
            $item_str = str_replace("{category}","", $item_str );
          }
          $item_str = str_replace("{date}",$item->get_date($instance['dateformat']), $item_str );
          $time = $item->get_date('U');
          $now = date('U');
          $tg = $now - $time;
          if ($tg < 60*60){
            $t =  floor(($tg+30)/60);
            $date_gap =$t.'m'/*_n('minute','minutes',$t,'multi_rss_reader')*/.$befl;
          }else if ($tg < 60*60*24){
            $t=floor(($tg+1800)/60/60);
            $date_gap = $t.'h'/*_n('hour','hours',$t,'multi_rss_reader')*/.$befl;
          }else if ($tg < 60*60*24*30){
            $t=floor(($tg+3600*12)/60/60/24);
            $date_gap = $t.'d'/*_n('day','days',$t,'multi_rss_reader')*/.$befl;
          }else if ($tg < 60*60*24*30*12){
            $t=floor(($tg+3600*24*15)/60/60/24/30);
            $date_gap = $t.'m'/*_n('month','months',$t,'multi_rss_reader')*/.$befl;
          }else {
            $t=floor(($tg+3600*24*30*6)/60/60/24/30/12);
            $date_gap = $t.'y'/*_n('year','years',$t,'multi_rss_reader') */.$befl;
          }
          $item_str = str_replace("{date_gap}",$date_gap, $item_str );

          $enclosure =$item->get_enclosure()->get_link();
          $enclosure = preg_replace("/\?#$/","",$enclosure);
          $description = $this->handling_description($item->get_description(), $img);

          if ($enclosure && strlen($enclosure) > 10){
            $img = array($enclosure , "" ,"");
          }
          if ($img[0] && strlen($img[0]) > 10 && ($img[1] !== '' || $img[1] > 10)){
            $item_str = str_replace("{img}",$img[0],$item_str);
            $item_str = str_replace("{img_width}",$img[1],$item_str);
            $item_str = str_replace("{img_height}",$img[2],$item_str);
          }else{
            //imgタグの <div>エリアごと除く
            $item_str = preg_replace("/<[^>]+{img}[^>]+><\/[^>]+>/i","" , $item_str);
          }

          $item_str = str_replace("{description}",(mb_strimwidth($description,0,$instance['size'],$instance['morestr'])), $item_str );

          $i++;
          $item_str = str_replace("{index}",$i ,$item_str );
          // echo $i. ":" .$item_str .":". $item->get_title()."/".$instance['items_format']."/".$instance['item_format']."<br>";
          $inner .= $item_str;
        }

        echo str_replace("{lists}",$inner, get_option($this->option_prefix.'items_format'));// $instance['items_format']

      } catch (Exception $e) {
        echo "例外キャッチ：". $e->getMessage(). "\n";
      }
      $this->print_footer();
      echo $after_widget."\n";
    }else{
      echo $before_widget."\n";
      echo $before_title.$title.$after_title."\n";
      echo str_replace("{lists}","<li><div>".__('no rss resouces','multi_rss_reader') ."</div></li>", $instance['$items_format']);
      $this->print_footer();
      echo $after_widget."\n";
    }

    wp_reset_query();
  }

  function handling_description($value,&$imgs){
    //pick out img
    preg_match("/<img +.*src=['\"]([^'\"]+)['\"]/",$value,$img);
    preg_match("/(?:width=['\"]?([0-9%]+)?['\"]?)/",$value,$width);
    preg_match("/(?:height=['\"]?([0-9%]+)?['\"]?)/",$value,$height);

    //remove tags
    $value = preg_replace("/<[^>]+>/i","",$value);

    $imgs = array($img[1] , $width[1] ,$height[1]);
    return $value;
  }

  function check_input_layoutformat($id, $msgid){
    if (isset($_POST[$id]) && check_admin_referer('multi-rss-reader-options')){
      $temp = stripslashes ($_POST[$id]);
      if (!$temp || (strpos($temp,"{title}") === false
                     && strpos($temp,"{link}") === false
                     && strpos($temp,"{description}") === false
                     )
          ) {
        $this->my_error_msg[$msgid] = '<span style="color:#ff0000;">'.__( 'There is no one {title} {link} {description} of here.', 'multi_rss_reader' ).'</span>';
      }else{
        update_option($this->option_prefix.$id , $temp);
      }
    }
  }


  function print_form_input($label,$id , $instance, $sub_letter , $plus_attr , $input_size ='',$err_msg="" ){
    $idf = $this->get_field_id($id);
    $idname = $this->get_field_name($id);
    $value = $instance[$id];

    if ($label !== ''){
      echo "<tr><td><label for='$idf'>";
      _e($label);
      echo "";
      echo "</label><br>$sub_letter</td><td>";
    }

    echo " <input class='widefat' id='$idf' name='$idname' type='text' value='".esc_attr($value)."' ";
    if ($input_size !== ''){
      echo "size='$input_size' maxlength='$input_size' width='60' ";
    }
    echo "$plus_attr/>";
    $this->print_error_msg($err_msg,"<br>");
    if ($label !== ''){
      echo "</td></tr>";
    }
  }

  function print_layout($instance){
    $id = 'layouttype';
    $idf = $this->get_field_id($id);
    $idname = $this->get_field_name($id);
    $value = $instance[$id];
    $checked = array_pad(array(),4,'');
    //$checked = array_pad('',4);
      if (!$value || $value === ''){
          $value = 2;
      }
    $checked[$value] = " checked='checked' ";
    ?>
    <style>
 input.mrr{
  padding:1px;
  border:3px;
  margin:1px;
  display:none;
 }

  input.mrr+label{
  padding:0px;
 border:0px;
 margin:0px;
  }

  image.mrr{
  padding:0px;
 border:0px;
 margin:0px;
  }

  input[type="radio"]:checked+label img.mrr{
    opacity:1.0;
    filter: alpha(opacity=100);
    -ms-filter: "alpha( opacity=100 )";
  }
  input[type="radio"]+label img:hover.mrr{
    opacity:1.0;
    filter: alpha(opacity=100);
    -ms-filter: "alpha( opacity=100 )";
  }
  input[type="radio"]+label img.mrr{
    opacity: 0.2;
    filter: alpha(opacity=20);
    -ms-filter: "alpha( opacity=20 )";
  }

  input[type="radio"].mrr {
    visibility:hidden;
  }

</style>

<?php
     $pip = plugin_dir_url(__FILE__) . "image/";
echo '<input id="'.$idf.'1" type="radio" name="'.$idname.'" class="mrr" value="1" '.$checked[1].'/>';
echo '<label for="'.$idf.'1" onClick="targetClicked(1)"><img src="'.$pip.'title_line.png" value="1" class="mrr"></label>';
echo '<input id="'.$idf.'2" type="radio" name="'.$idname.'" class="mrr" value="2" '.$checked[2].'/>';
echo '<label for="'.$idf.'2" onClick="targetClicked(2)"><img src="'.$pip.'magazinelayout.png" value="2" class="mrr"></label>';
echo '<input id="'.$idf.'3" type="radio" name="'.$idname.'" class="mrr" value="3" '.$checked[3].'/>';
echo '<label for="'.$idf.'3" onClick="targetClicked(3)"><img src="'.$pip.'cardlayout.png" value="3" class="mrr"></label>';
  }

  function put_layout_change_script(){
  ?>
  <script>

  // イベントリスナー
  function targetClicked(ti)
  {
      for (i = 1 ;i <= label.length ; i++)
    {
      document.getElementById("tabs-"+i).setAttribute("display","none");
      document.getElementById("tabs-"+i).style.display = "none";
      document.getElementById("tabs-"+i+"-label").setAttribute("display","none");
      document.getElementById("tabs-"+i+"-label").style.display = "none";
      document.getElementById("tabs-"+i+"-textarea").setAttribute("display","none");
      document.getElementById("tabs-"+i+"-textarea").style.display = "none";
     //document.getElementById("tabs-1").setAttribute("visibility","hidden");
      //document.getElementById("tabs-1").style.visibility = "hidden";
    }
    document.getElementById("tabs-"+ti).setAttribute("display","block");
    document.getElementById("tabs-"+ti).style.display = "block";
    document.getElementById("tabs-"+ti+"-label").setAttribute("display","block");
    document.getElementById("tabs-"+ti+"-label").style.display = "block";
    document.getElementById("tabs-"+ti+"-textarea").setAttribute("display","block");
    document.getElementById("tabs-"+ti+"-textarea").style.display = "block";
  }

var labelt = new Array("tabs-1", "tabs-2", "tabs-3");
var label = new Array("atabs-1", "atabs-2", "atabs-3");

     <?php
     $id = 'layouttype';
     $idf = $this->get_field_id($id);
     echo "
      if (document.getElementById('".$idf."1').checked){
          targetClicked(1);
      }
      if (document.getElementById('".$idf."2').checked){
          targetClicked(2);
      }
      if (document.getElementById('".$idf."3').checked){
          targetClicked(3);
      }
     ";
     ?>
</script>

  <?php
  }

  function print_footer(){
    ?>
        <div align="right" style="font-size:xx-small;margin:2pt;">
            <a href="http://edutainment-fun.com/hidemaru/wordpress/multi-rss-reader-widget-wordpress_891.html">Multi RSS Reader</a>
        </div>
    <?php
  }

  function print_error_msg($err_msg, $br){
    if ($err_msg && strlen( $err_msg ) > 1){
      echo "$br<font color='#ff0000'>$err_msg</font>";
    }
  }

  function check_num_range(&$instance_id , $min , $max , $def){
    $instance_id = mb_convert_kana($instance_id,"as");
    $instance_id = trim($instance_id);
    if($instance_id < $min or $instance_id > $max or !is_numeric ( $instance_id )){
      $instance_id = $def;
      return false;
    }
    return true;
  }

  function just_fit_str($one , $two){
    if ($one{0} != $two{0}){
      return false;
    }
    if (strlen($one) != strlen($two)){
      return false;
    }

    return (strpos("@".$one."@", "@".$two."@") === 0);
  }

  function delete_options(){
    delete_option($this->option_prefix.'items_format' );
    delete_option($this->option_prefix.'item_format' );
    delete_option($this->option_prefix.'item_format_titlelayout' );
    delete_option($this->option_prefix.'item_format_cardlayout' );
    delete_option($this->option_prefix.'remove_title_pattern' );
  }

  function set_default_widget_value(&$instance){
    $instance = wp_parse_args((array)$instance
                              , array('title' => 'Multi-RSS Reader'
                                      ,'number' => 10
                                      ,'size' => 80
                                      ,'morestr' => '..'
                                      ,'dateformat' => 'y/m/d H:i'
                                      ,'cachetime' => 1440
                                      ));
  }

  /*
   $user = get_user_by( 'login', 'ユーザー名' );

でユーザーのデータを取得して、

$user->ID
   */

  function set_default_option_value(){
    $items_format = "<div style='height:400px;overflow: auto;'><ul class='recentEntries'>{lists}</ul></div>";
    update_option($this->option_prefix.'items_format' , $items_format);

    $item_format_titlelayout= "<li><span><a href='{link}' target='_blank' style='font-weight:bold;'>{title}</a></span>".
        "<span style='font-size:xx-small'> {date_gap}</span></li>";
    update_option($this->option_prefix.'item_format_titlelayout' , $item_format_titlelayout);

    $item_format = "<li><div style='background-image: url(\"{img}\"); float:left; display:block; opacity:1; width:50pt; height:50pt; background-size:cover; margin:3pt;'></div>".
        "<div><a href='{link}' target='_blank' style='font-weight:bold;'>{title}</a></div>".
        "<div>{description}</div>".
        "<div style='font-size:xx-small'>{sitetitle}:{author}:{category} {date} {date_gap}</div><br></li>";
    update_option($this->option_prefix.'item_format' , $item_format);

    $item_format_cardlayout = "<li style='float:left;'><table style='width:198pt;height:240pt;' style='position:relative;'>
      <tr><td style='border:solid 1px #000;' style='width:198pt; height:240pt;' ><div style='background-image: url(\"{img}\"); float:left; display:block; opacity:1; width:190pt; height:120pt; background-size:cover; margin:3pt;'></div><div><a href='{link}' target='_blank' style='font-weight:bold;'>{title}</a></div><div>{description}</div><div style='font-size:xx-small'>{sitetitle}:{author}:{category} {date} {date_gap}</div></td></tr></table></li>";
    update_option($this->option_prefix.'item_format_cardlayout' , $item_format_cardlayout);

    $remove_title_pattern = "/^\[?([pP][rR]|宣伝|広告)\]?/";
    update_option($this->option_prefix.'remove_title_pattern' , $remove_title_pattern);
  }
}//end of class

function multi_rss_reader_Init() {
  //ウィジェットのクラス名を登録
  register_widget('multi_rss_reader');
}

add_action('widgets_init', 'multi_rss_reader_Init');



?>