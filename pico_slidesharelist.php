<?php
/**
 * Pico Slideshare Slide List
 * Slideshareのスライドリストを取得しページとして追加するプラグイン
 *
 * @author TakamiChie
 * @link http://onpu-tamago.net/
 * @license http://opensource.org/licenses/MIT
 * @version 1.0
 */
class Pico_SlideshareList {

  public function config_loaded(&$settings) {
    $apikey = $settings["slideshare"]["apikey"];
    $secret = $settings["slideshare"]["secret"];
    $user = $settings["slideshare"]["username"];
    $dir = $settings["slideshare"]["directory"];
    $base_url = $settings['base_url'];
    $cdir = ROOT_DIR . $settings["content_dir"] . $dir;
    $cachedir = CACHE_DIR . "slideshare/";
    $cachefile = $cachedir . "slides.xml";
    if(!file_exists($cachedir)){
      mkdir($cachedir, "0500", true);
    }
		$list_url = "https://www.slideshare.net/api/2/get_slideshows_by_user?";

    if(file_exists($cachefile)){
      $filetime = new DateTime();
      $filetime->setTimestamp(filemtime($cachefile));
      $filetime->modify("+30 min");
      $now = new DateTime();
      if($filetime > $now){
        // キャッシュ有効時は、読み取り処理自体が不要なためスキップ
        return;
      }
    }else{
      // キャッシュ無効なため、以前作成したファイルを全削除
	    if($handle = opendir($cdir)){
        while(false !== ($file = readdir($handle))){
          if(!is_dir($file) && $file != "index.md"){
            unlink($cdir. "/" . $file);
          }
        }
        closedir($handle);
	    }
    }
    /* テキストファイル作成処理 */
    try{
      // まずはXML読み込み
      $ts = time();
      $params = array(
        "username_for" => $user,
        "api_key" => $apikey,
        "ts" => $ts,
        "hash" => sha1($secret . $ts)
      );
      $content = $this->curl_getcontents($list_url . http_build_query($params));

      $xml = new SimpleXMLElement($content);
      if(!$xml->Slideshow){
        throw new Exception($xml->Message);
      }
      file_put_contents($cachefile, $content);

      foreach($xml->Slideshow as $s){
        // mdファイル作成
        $t = array();
        array_push($t, $s->Language);
        array_push($t, $s->Format);
        if($s->Download) array_push($t, "downloadable");
        $page = "/*\n";
        $page .= sprintf("  Title: %s\n", $s->Title);
        $page .= sprintf("  Author: %s\n", $s->Username);
        $page .= sprintf("  Date: %s\n", $s->Created);
        $page .= sprintf("  Description: %s\n", $s->Description);
        $page .= sprintf("  URL: %s\n", $s->URL);
        $page .= sprintf("  Image: %s\n", strpos($s->ThumbnailURL, "//", 0) === 0 ? 
          "http:" . $s->ThumbnailURL : $s->ThumbnailURL);
        $page .= sprintf("  Tag: %s\n", implode(", ", $t));
        $page .= "*/\n";
        $page .= htmlspecialchars_decode($s->Embed);

        file_put_contents($cdir . $s->ID . ".md", $page);
      }
    }catch(Exception $e){
      $page = "/*\n";
      $page .= sprintf("  Title: %s\n", "Slideshare Access Error");
      $page .= sprintf("  Description: %s\n", "Slideshare接続処理でエラーが発生しました。" . $e->getMessage());
      $page .= "*/\n";
      $page .= "Slideshareに接続できませんでした。\n";
      $page .= $e->getMessage();
      file_put_contents($cdir . "error.md", $page);
    }
	}
  
  private function curl_getcontents($url)
  {
    $ch = curl_init();
    curl_setopt_array($ch, array(
      CURLOPT_URL => $url,
      CURLOPT_TIMEOUT => 10,
    	CURLOPT_CUSTOMREQUEST => 'GET',
    	CURLOPT_SSL_VERIFYPEER => FALSE,
    	CURLOPT_RETURNTRANSFER => TRUE,
    	CURLOPT_USERAGENT => "Pico"));

    $content = curl_exec($ch);
    if(!$content){
      throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    return $content;
  }
}

?>
