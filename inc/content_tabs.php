<?php
if (!defined('DTC_VERSION')) die('Invalid call');

global $sitepress;

$options = get_option('dtc_options');

$txts = new DTCTexts($options);

$currentLang = ICL_LANGUAGE_CODE;
$defaultLang = $sitepress->get_default_language();
$languages = array($defaultLang => '');
$a = icl_get_languages('skip_missing=0&orderby=code');
foreach ($a as $v) { $languages[$v['language_code']] = $v['native_name']; }
$translatedLangs = $languages;
unset($translatedLangs[$defaultLang]);


$postTypes = array();
$selTypes = $options['types'];
$allTypes = get_post_types('', 'objects');
foreach ($allTypes as $type) {
    if (in_array($type->name, $selTypes)) {
        $postTypes[$type->name] = $type->label;
    }
}

$postType = isset($_GET['type']) ? $_GET['type'] : key($postTypes);

$basePostIds = array(); //array of postId
$posts = array(); // [postId] => [lang], [title], [excerpt], [body]
$translations = array(); // [postId][lang] => transId

if ($postType) {

    //SETUP posts and translations array
    foreach ($languages as $lang => $v) {
      $sitepress->switch_lang($lang);

      $args = array(
        'post_type' => $postType,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
      );
      $query = new WP_Query( $args );

      if ($query->have_posts()) {
      while ($query->have_posts()) {
        $query->the_post();
        $id = get_the_ID();
        $title = get_the_title();
        $content = get_the_content();
        $content = trim(preg_replace('/\[.*?\]/', '', strip_tags($content))); //strip shortcodes
        if ($lang == $defaultLang) {
          if ('' == $content) continue;
          $basePostIds[] = $id;
        }
        $content = wp_trim_words($content, 50, '');
        $posts[$id] = array('lang' => $lang, 'title' => $title, 'content' => $content);
      }}
      wp_reset_postdata();
    }
    $sitepress->switch_lang($currentLang);

    foreach ($basePostIds as $postId) {
      foreach ($translatedLangs as $lang => $name) {
        $translations[$postId][$lang] = icl_object_id($postId, $postType, false, $lang);
      }
    }

    $detectDefaultLang = $options['detect_default_lang'];

}


?>
<div class="wrap">
<h2>Translation check</h2>


<h2 class="nav-tab-wrapper">
<?php foreach ($postTypes as $k => $v): ?>
<a class="nav-tab<?php if ($k == $postType) echo ' nav-tab-active' ?>" href="admin.php?page=<?php echo DTC_SLUG ?>-content&amp;type=<?php echo $k ?>"><?php echo $v ?></a>
<?php endforeach ?>
</h2>

<p>This page shows an overview of your content (primary language and translations).
</br> Please click on <b>Detect language</b> to perform the language detection on your content.</p>

<div id="dtc-legend" style="display:none">
<table border="0" cellpadding="8" cellspacing="0" class="dtc-legend"><tr>
<td>Language match</td>
<td class="detection-success bg">&nbsp;</td>
<td>Language mismatch</td>
<td class="detection-error bg">&nbsp;</td>
<td>Manually review <br><small>(language probability &lt; 95%)<small></td>
<td class="detection-warning bg">&nbsp;</td>
<td></td>
<td></td>
</tr></table>

</div>

<form class="frm-check" method="POST">
    <input type="submit" class="button button-primary button-hero submit" value="Detect language" />
    <input type="hidden" name="autocheck" value="1" />
    <span class="progress" style="display:none">
    &nbsp;Detecting languages, please wait&hellip; <br><img src="<?php echo plugins_url('img/ajax-loader.gif', dirname(__FILE__)) ?>" alt="loading" />
    </span>
</form>

<div class="error dtc-error" style="display:none"></div>




<table border="0" cellpadding="8" cellspacing="0" id="dtc-content" class="wp-list-table widefat">
<thead>
<tr>
    <th scope="col"><?php echo $languages[$defaultLang] ?> </th>
    <?php foreach ($translatedLangs as $k => $v) : ?>
    <th scope="col"><?php echo $v ?></th>
    <?php endforeach ?>
</tr>
</thead>

<?php $i=-1; foreach ($basePostIds as $postId):
    $i++;
    $post = $posts[$postId];
    if ($detectDefaultLang) $txts->addContent($postId, $post['title'], $post['content']);
?>
<tr <?php echo $i % 2 == 0 ? 'class="alternate"' : '' ?>>
    <td id="post-<?php echo $postId ?>" data-lang="<?php echo $defaultLang ?>">
        <a href="<?php echo get_edit_post_link($postId) ?>"><?php echo ($post['title']) ?></a>
    </td>
    <?php foreach ($translatedLangs as $lang => $langName) :
        $exists = !empty($translations[$postId][$lang]) && isset($posts[$translations[$postId][$lang]]);
        if ($exists):

        $transId = $translations[$postId][$lang];
        $transPost = $posts[$transId];
        $txts->addContent($transId, $transPost['title'], $transPost['content']);
  ?>
    <td id="post-<?php echo $transId?>" data-lang="<?php echo $lang ?>">
    <?php
        echo '<a href="' . get_edit_post_link($transId) . '">' . ($transPost['title']) . '</a>';
        if (!$transPost['content']) echo ' <span class="missing">empty</span>';
        //else echo '<br/><small>' . wp_trim_words($transPost['content'], 10, '') . '</small>';
    ?>
    </td>
   <?php else: ?>
   <td><span class="missing">missing</span></td>
   <?php endif; ?>
    <?php endforeach ?>
</tr>
<?php endforeach ?>


</table>

<form class="frm-check" method="POST">
    <input type="submit" class="button button-primary button-hero submit" value="Detect language" />
    <input type="hidden" name="autocheck" value="1" />
    <span class="progress" style="display:none">
    &nbsp;Detecting languages, please wait&hellip; <br><img src="<?php echo plugins_url('img/ajax-loader.gif', dirname(__FILE__)) ?>" alt="loading" />
    </span>
</form>


</div>

<script type="text/javascript">

var minProb = <?php echo DTC_MIN_PROB ?>;
var textsToDetect = <?php echo json_encode($txts->getTexts()) ?>;
var reload = false;

function checkTranslations($) {
    var $f = $(".frm-check"),
        $progress = $f.find('.progress'),
        $error = $('.dtc-error'),
        $legend = $("#dtc-legend"),
        $t = $('#dtc-content');

    $progress.show();
    $error.hide();
    $t.find('td').removeClass('detection-warning detection-success detection-error');
    $t.find('span.detection').remove();

    var data = {
      'action': 'send_texts',
      'texts': textsToDetect
    };

    $.post(ajaxurl, data, function(response) {
        reload = true;
        $progress.hide();
        if (response.success) {
            var data = response.data;
            //console.info(data);
            $.each(data, function (k, v) {
                var $td = $('#post-'+k);
                if (!$td.length) return true;
                var lang = $td.attr('data-lang'),
                    unknown = v.lang ? false : true,
                    correct = v.lang && v.lang == lang,
                    reliable = v.prob && v.prob > minProb;
                if (unknown) {
                    $td.append('<span class="detection detection-unknown">?</span>');
                    console.log(textsToDetect[k]+': '+unknown);
                } else {
                    if (!reliable) {
                        $td.addClass('detection-warning');
                        console.log(textsToDetect[k]+': '+v.lang+' ('+v.prob+')');
                    } else {
                        $td.addClass( correct ? 'detection-success' : 'detection-error' );
                    }
                    var badgeClass = correct ? 'detection-correct' : 'detection-incorrect';
                    $td.append('<span class="detection '+badgeClass+'">'+v.lang+'</span>');
                }
            });
            $legend.show();
        } else {
            $error.html('<p>ERROR: '+response.msg+'</p>').show();
        }
    }, 'json');
}

(function ($) {

$('.frm-check').on('submit', function (e) {
    if (reload) return true;
    checkTranslations($);
    return false;
});

if (<?php echo !empty($_POST['autocheck']) ? 'true' : 'false' ?>) {
    checkTranslations($);
}

}(jQuery));
</script>