<?php 
/* 
    Author: I. Kancijan
*/

global $sitepress;
$error = array();
$numb_of_prop = 0;
$posts_per_page = -1;
$title_max_chars = 64;
$description_max_chars = 2000;
$max_imgs = 12;

//changes to the default language
$sitepress->switch_lang( $sitepress->get_default_language() );

// Get all properties
$args = array(
    'post_type'   => 'estate',
    'post_status' => 'publish',
    'posts_per_page' => $posts_per_page,
    'fields' => 'ids',
    'suppress_filters' => false
);
$wp_query = new WP_Query( $args );
  
// WPML - changes to the current language
$sitepress->switch_lang( 'hr' );
//dump_data("sitepress",$sitepress);

// Debug func.
function dump_data($name,$data, $pre = true){

    echo $name.': <br>';
    if($pre) echo "<pre>";
    print_r($data);
    if($pre) echo "</pre>";
    echo '----<br>';
}

// Get required data from all properties
function nd_get_meta(){

    global $wp_query, $title_max_chars, $description_max_chars;
    $posts = $wp_query->posts;
    $output = array();

    // Loop through all properties
    foreach($posts as $p){

        $p_array = $p_data = $p_meta = $p_fields = $p_en_id = $p_en_data = $p_de_id = $p_de_data = array();

        // Get translations
        // hr
        $p_data = get_post( $p );
        $p_meta = get_post_meta( $p );
        $p_fields = get_fields($p_data->ID);

        // en
        $p_en_id = icl_object_id( $p , 'page', false, 'en' );

        if(!empty($p_en_id)) $p_en_data = get_post($p_en_id);

        // de
        $p_de_id = icl_object_id( $p , 'page', false, 'de' );

        if(!empty($p_de_id)) $p_de_data = get_post($p_de_id);

        $property_type = wp_get_post_terms($p_data->ID, 'property-type', array("fields" => "all"));
        $location = wp_get_post_terms($p_data->ID, 'city', array("fields" => "all"));
        // dump_data("location",$location);
        // dump_data("p_data",$p_data);
        // dump_data("p_meta",$p_meta);
        // dump_data("property_type",$property_type);
        // dump_data("get_fields",get_fields($p_data->ID));
        // dump_data("p_en_data",$p_en_data);

        // Property array
        $p_array["property_id"] = (is_numeric($p_data->ID))? $p_data->ID : '';
        $p_array["date_listed"] = get_the_time('c', $p_data->ID);
        $p_array["property_type"] =  $property_type[0]->description;
        $p_array["listing_type"] = wp_get_post_terms($p_data->ID, 'offer-type', array("fields" => "all"))[0]->description;
        $p_array["price"] = $p_fields["estate_attr_price"];
        $p_array["location"] = $location[0];

        if(isset($p_fields['estate_location']['lat'])){
            $p_array["geox"] = $p_fields['estate_location']['lat'];
        }
        if(isset($p_fields['estate_location']['lng'])){
            $p_array["geoy"] = $p_fields['estate_location']['lng'];
        }

        $p_array["property_size"] = sprintf("%.2f", $p_fields['estate_attr_property-size']);

            // hr
            if (strlen($p_data->post_title) > $title_max_chars) {
                $post_title = substr($p_data->post_title, 0, $title_max_chars-3) . '...';
            }else{
                $post_title = $p_data->post_title;
            }

            $p_array["title"] = $post_title;

            if (strlen($p_data->post_content) > $description_max_chars) {
                $post_content = substr($p_data->post_content, 0, $description_max_chars-3) . '...';
            }else{
                $post_content = $p_data->post_content;
            }

            $p_array["description"] = htmlspecialchars($post_content);

            // en
            if($p_en_data){

                // description
                if(!empty($p_en_data->post_content)){

                    if (strlen($p_en_data->post_content) > $description_max_chars) {
                        $post_content_en = substr($p_en_data->post_content, 0, $description_max_chars-3) . '...';
                    }else{
                        $post_content_en = $p_en_data->post_content;
                    }

                    $p_array["description-EN"] = htmlspecialchars($post_content_en);
                    // dump_data("description-EN", $post_content_en);
                }
            }

            // de
            if($p_de_data){

                // description
                if(!empty($p_de_data->post_content)){

                    if (strlen($p_de_data->post_content) > $description_max_chars) {
                        $post_content_de = substr($p_de_data->post_content, 0, $description_max_chars-3) . '...';
                    }else{
                        $post_content_de = $p_de_data->post_content;
                    }
                    $p_array["description-DE"] = htmlspecialchars($post_content_de);
                }
            }

        $p_array["bathrooms"] = $p_fields["estate_attr_bathrooms"];
        $p_array["bedrooms"] = $p_fields["estate_attr_bedrooms"];
        $p_array["link"] = $p_data->guid;
        $p_array["images"] = $p_fields["estate_gallery"];

        array_push($output, $p_array);
    }
    //dump_data("output", $output);
    return $output;
}

function create_crozilla_xml(){

    global $numb_of_prop;
    $meta = nd_get_meta();

    $dom = new DOMDocument('1.0', 'utf-8');
    // $dom->preserveWhiteSpace = FALSE;
    
    $properties = $dom->createElement('properties');
    $dom->appendChild($properties);


    foreach ($meta as $key => $value) {

        global $error;
        $current_errors = array();

        (empty($value["listing_type"]) ? array_push($current_errors, $value["property_id"]." - empty listing type") : '');

        if(!empty($current_errors)){

            array_push($error, $current_errors);
        }else{

            $property = $dom->createElement('property');
            $properties->appendChild($property);

                $data = $dom->createElement('property-id',  htmlspecialchars($value["property_id"]));
                $property->appendChild($data);
                $data = $dom->createElement('date-listed',  htmlspecialchars($value["date_listed"]));
                $property->appendChild($data);
                $data = $dom->createElement('property-type',  htmlspecialchars($value["property_type"]));
                $property->appendChild($data);
                $data = $dom->createElement('listing-type',  htmlspecialchars($value["listing_type"]));
                $property->appendChild($data);

                $location = $dom->createElement('location');
                $property->appendChild($location);

                    $postal = $dom->createElement('postal-code',  htmlspecialchars($value["location"]->description));
                    $location->appendChild($postal);
                    $city = $dom->createElement('city',  htmlspecialchars($value["location"]->name));
                    $location->appendChild($city);
                    $geox = $dom->createElement('geox',  htmlspecialchars($value["geox"]));
                    $location->appendChild($geox);
                    $geoy = $dom->createElement('geoy',  htmlspecialchars($value["geoy"]));
                    $location->appendChild($geoy);

                $property_size = $dom->createElement('property-size');
                $property->appendChild($property_size);

                    $number = $dom->createElement('number',  htmlspecialchars($value["property_size"]));
                    $property_size->appendChild($number);

                $price = $dom->createElement('price');
                $property->appendChild($price);

                    $amount = $dom->createElement('amount',  htmlspecialchars($value["price"]));
                    $price->appendChild($amount);

                $images = $dom->createElement('images');
                $property->appendChild($images);

                    $imgs = array();
                    $imgs = $value["images"];
                    global $max_imgs;
                    $i = 0;
                    foreach ($imgs as $key => $val) {

                        if(++$i > $max_imgs) break;

                        $image = $dom->createElement('image',  htmlspecialchars($val["url"]));
                        $images->appendChild($image);
                    }

                $features = $dom->createElement('features');
                $property->appendChild($features);

                    $bathrooms = $dom->createElement('bathrooms',  htmlspecialchars($value["bathrooms"]));
                    $features->appendChild($bathrooms);
                    $bedrooms = $dom->createElement('bedrooms',  htmlspecialchars($value["bedrooms"]));
                    $features->appendChild($bedrooms);

                // hr
                $data = $dom->createElement('title',  htmlspecialchars($value['title']));
                $property->appendChild($data);

                $data = $dom->createElement('description',  htmlspecialchars($value['description']));
                $property->appendChild($data);

                // en
                if(isset($value['title-EN']) && !empty($value['title-EN'])){

                    $data = $dom->createElement('title-EN',  htmlspecialchars($value['title-EN']));
                    $property->appendChild($data);
                }

                if(isset($value['description-EN']) && !empty($value['description-EN'])){

                    $data = $dom->createElement('description-EN',  htmlspecialchars($value['description-EN']));
                    $property->appendChild($data);
                }

                // de
                if(isset($value['title-DE']) && !empty($value['title-DE'])){

                    $data = $dom->createElement('title-DE',  htmlspecialchars($value['title-DE']));
                    $property->appendChild($data);
                }

                if(isset($value['description-DE']) && !empty($value['description-DE'])){

                    $data = $dom->createElement('description-DE',  htmlspecialchars($value['description-DE']));
                    $property->appendChild($data);
                }

                $data = $dom->createElement('link', $value['link']);
                $property->appendChild($data);

                $numb_of_prop++;
            }
    }
    //Save XML as a file
    $dom->save('crozilla.xml');

    return 'done';
}

?>
<?php 
echo '<p>Status: '.create_crozilla_xml().'</p><p>Number of properties: '.$numb_of_prop.'</p>'; ?>
<p>Errors:</p>
<p><pre><?php print_r($error); ?></pre></p>
<a href="<?php echo get_home_url(); ?>crozilla.xml" target="_blank">XML File</a>
