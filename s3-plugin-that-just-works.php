<?php
//ini_set('display_errors',1);
//error_reporting(E_ALL ^ E_NOTICE);
?>
<?php
/*
Plugin Name: S3 Plugin That Just Works
Plugin URI: #
Description: Copies all uploaded attachments to S3
Author: Dan Mandle
Version: 0.0.1
Author URI: http://danmandle.com
Last Updated: 3/16/2013
*/

define('AWS_KEY', '################');
define('AWS_SECRET_KEY', '##################################');
$bucket = "##############";

/*
function getUploadedAttachmentID($attachmentID) {
    //wp_handle_upload( $uploadedfile);
    //update_option('S3_Logging', print_r($results,1) );
	//update_option('S3_Logging', print_r(wp_get_attachment_image( $results ) ),1);
}
add_action('add_attachment', 'getUploadedAttachmentID');
add_action('edit_attachment', 'getUploadedAttachmentID');
*/

function uploadAttachmentToS3($attachInfo){
	global $awsKey;
	global $awsSecret;
	global $bucket;
	
	require_once(dirname(__FILE__)."/aws-sdk/sdk.class.php");
	$s3 = new AmazonS3(array(
	        'key' => AWS_KEY,
	        'secret' => AWS_SECRET_KEY
		));
	
	$uploadDir = wp_upload_dir();

	//upload full size
	$file = $uploadDir['basedir']."/".$attachInfo['file'];

	$s3FilePath = "uploads".$uploadDir['subdir']."/".basename($file);
	$response = $s3->create_object($bucket, $s3FilePath, array(
	    'fileUpload' => $file,
	    'acl' => AmazonS3::ACL_PUBLIC,
	    'contentType' => 'text/plain',
	    'storage' => AmazonS3::STORAGE_REDUCED,
	));

	$log[] = $response->header['_info']['url'];

	//upload all smaller sizes
	foreach($attachInfo['sizes'] as $imgSize){
		$file = $uploadDir['path']."/".$imgSize['file'];

		$s3FilePath = "uploads".$uploadDir['subdir']."/".basename($file);
		$response = $s3->create_object($bucket, $s3FilePath, array(
		    'fileUpload' => $file,
		    'acl' => AmazonS3::ACL_PUBLIC,
		    'contentType' => 'text/plain',
		    'storage' => AmazonS3::STORAGE_REDUCED,
		));
		$log[] = $response->header['_info']['url'];
	}

	update_option('S3_Logging', print_r($log,1) );

	return $attachInfo;
}
add_filter('wp_update_attachment_metadata', 'uploadAttachmentToS3');

function changeAttachmentURLtoS3($url) {
	// $url is the absolute http path to the image

	global $bucket;
	$uploadDir = wp_upload_dir();
	
	$path = str_replace($uploadDir['baseurl'], '', $url);
	$url = "http://".$bucket.".s3.amazonaws.com/uploads".$path;
	
	return $url;
}
add_filter('wp_get_attachment_url', 'changeAttachmentURLtoS3');

function deleteFromS3($post_id){
	global $bucket;
	require_once(dirname(__FILE__)."/aws-sdk/sdk.class.php");
	$s3 = new AmazonS3(array(
	        'key' => AWS_KEY,
	        'secret' => AWS_SECRET_KEY
		));

	$sizes = get_intermediate_image_sizes();
	$sizes[] = 'full';

	foreach($sizes as $size){

		$image = str_replace('http://'.$bucket.'.s3.amazonaws.com/', '', wp_get_attachment_image_src( $post_id, $size ));
		$images[]['key'] = $image[0];
	}

	$response = $s3->delete_objects($bucket, array(
			'objects' => $images
		));

	update_option('S3_Logging', print_r($response,1) );
}
add_action('delete_attachment', deleteFromS3);
?>