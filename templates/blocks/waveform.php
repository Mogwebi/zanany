<?php
/**
 * Waveform
 */

defined( 'ABSPATH' ) || exit;
$post_id = get_the_ID();
$data = get_post_meta($post_id, 'waveform_data', true);
$duration = get_post_meta($post_id, 'duration', true);

if(!$data || empty($data) || count($data) < 10){
	return;
}

$preview = (int)play_get_preview($post_id) * 1000;

if($preview && $preview < $duration){
	$count = count($data) * ($preview/$duration);
	$data = array_slice($data, 0, round($count));
	$duration = $preview;
}
?>
<div class="waveform" data-waveform="<?php echo esc_attr(implode(',',$data)); ?>" data-duration="<?php echo esc_attr($duration); ?>" data-id="<?php echo esc_attr($post_id); ?>">
	<button class="btn-play btn-play-waveform" data-play-id="<?php echo esc_attr($post_id); ?>"></button>
	<span class="sep-1"></span>
	<div class="waveform-container"></div>
</div>
