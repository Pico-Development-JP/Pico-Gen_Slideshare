<?php
/**
 * Pico Slideshare Slide List
 * Slideshareのスライドリストを取得しページとして追加する自動更新モジュール
 *
 * @author TakamiChie
 * @link http://onpu-tamago.net/
 * @license http://opensource.org/licenses/MIT
 * @version 1.0
 */
class Pico_SlideshareList {
  
  public function run($settings) {
    if(empty($settings["slideshare"]) || 
      empty($settings["slideshare"]["apikey"]) ||
      empty($settings["slideshare"]["secret"]) ||
      empty($settings["slideshare"]["username"]) ||
      empty($settings["slideshare"]["directory"])) {
      return;
    }
    $apikey = $settings["slideshare"]["apikey"];
    $secret = $settings["slideshare"]["secret"];
    $user = $settings["slideshare"]["username"];
    $dir = $settings["slideshare"]["directory"];
    $cdir = ROOT_DIR . $settings["content_dir"] . $dir;
    $cachedir = LOG_DIR . "slideshare/";
    $cachefile = $cachedir . "slides.xml";
    if(!file_exists($cachedir)){
      mkdir($cachedir, "0500", true);
    }
		$list_url = "https://www.slideshare.net/api/2/get_slideshows_by_user?";

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
      $responce;
      // まずはJSON読み込み
      $content = $this->curl_getcontents($list_url . http_build_query($params));
      file_put_contents($cachefile, $content);
      $xml = new SimpleXMLElement($content);
      if(!$xml->Slideshow){
        throw new Exception($xml->Message);
      }
      $this->removeBeforeScanned($cdir);

      foreach($xml->Slideshow as $s){
        // mdファイル作成
        $t = array();
        array_push($t, $s->Language);
        array_push($t, $s->Format);
        array_push($t, "embed");
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
      echo "SlideShare Access Error\n";
      echo $e->getMessage() . "\n";
    }
	}

  /**
   *
   * ファイルをダウンロードする
   *
   * @param string $url URL
   * @param array $responce レスポンスヘッダが格納される配列(参照渡し)。省略可能
   *
   */
  private function curl_getcontents($url, &$responce = array())
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
    if(!curl_errno($ch)) {
      $responce = curl_getinfo($ch);
    } 
    if(!$content){
      throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    return $content;
  }

  /**
   *
   * 以前自動生成した原稿ファイルを全削除する
   *
   * @param string $cdir 対象のファイルが格納されているディレクトリパス
   *
   */
  private function removeBeforeScanned($cdir){
    if($handle = opendir($cdir)){
      while(false !== ($file = readdir($handle))){
        if(!is_dir($file) && $file != "index.md"){
          unlink($cdir. "/" . $file);
        }
      }
      closedir($handle);
    }
  }
}

?>
