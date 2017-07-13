<?php
$asin = isset($_GET['ASIN']) ? $_GET['ASIN'] : '';  

if (!empty($asin)) {
    $args = array(
        'meta_key' => 'amazon_id',
        'meta_value' => $asin,
        'posts_per_page' => -1);
    $posts = get_posts($args);
    $post_id = NULL;
    if ($posts) {
        foreach ($posts as $p) : 
            $post_id = $p->ID; break;
        endforeach;
    }
    if(!$post_id){
        $title = get_amazon_product_title($asin); 
        if ($title != NULL) {
            $post = array(
                'post_title' => $title,
                'post_content' => '[AMAZONPRODUCTS ASIN="' . $asin . '"]',
                 'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => 1
            );

            $post_id = wp_insert_post($post);
            update_post_meta($post_id, 'amazon_id', $asin);
        }
        
    }
    if ($post_id) {
        wp_redirect(get_permalink($post_id)); exit;
    }
}




function get_amazon_product_title($asin) {
	$query = array( "Operation"     =>"ItemLookup", 
                "ResponseGroup" 	=>"ItemAttributes,Offers,Images,Reviews,RelatedItems,Accessories,PromotionalTag",
               
                "RelatedItemPage" =>1,
                "IdType"   =>"ASIN", 
                "ItemId"      =>$asin,
               );
    $signed_url = sign_query($query);
    $ch = curl_init($signed_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 7); 
    $xml_string = curl_exec($ch);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);

    if($curl_info["http_code"]==200) { 
        $xml_obj = simplexml_load_string($xml_string);
        $array = json_decode(json_encode((array)$xml_obj), TRUE);
        $obj = $array["Items"]["Item"];
        $items = array_column($obj, 'Title');  
        return $items[0];
    }else {
        return NULL;
    }
}




function sign_query($parameters) {
$Access_Key_ID = "AKIAJF2QQBY57JD2HKTA";
$Associate_tag = "r09HxTI7mv0EgXGnYbj6V6InJA3VNzNRmB33pwDW";

    if(! $parameters) return "";
    $encoded_values = array();
    foreach($parameters as $key=>$val) {
        $encoded_values[$key] = rawurlencode($key) . "=" . rawurlencode($val);
    }
    $encoded_values["AssociateTag"]= "AssociateTag=".rawurlencode($Associate_tag);
    $encoded_values["AWSAccessKeyId"] = "AWSAccessKeyId=".rawurlencode($Access_Key_ID);
    $encoded_values["Service"] = "Service=AWSECommerceService";
    $encoded_values["Timestamp"] = "Timestamp=".rawurlencode(gmdate("Y-m-d\TH:i:s\Z"));
    $encoded_values["Version"] = "Version=2011-08-01";
    ksort($encoded_values);
    $server = "webservices.amazon.com";
    $uri = "/onca/xml"; //used in $sig and $url
    $method = "GET"; //used in $sig    
    $query_string = str_replace("%7E", "~", implode("&",$encoded_values)); 
    $sig = base64_encode(hash_hmac("sha256", "{$method}\n{$server}\n{$uri}\n{$query_string}", $Associate_tag, true));
    $url = "http://{$server}{$uri}?{$query_string}&Signature=" . str_replace("%7E", "~", rawurlencode($sig));
    return $url;

}