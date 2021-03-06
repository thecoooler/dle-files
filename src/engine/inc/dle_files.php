<?php

if ( !defined('DATALIFEENGINE') OR !defined('LOGGED_IN') ) {
    header('HTTP/1.1 403 Forbidden');
    header('Location: ../../');
    die('Hacking attempt!');
}

function save_con($filename, $data, $openfile = false, $prefix = "\t") {
    if( !$openfile ) {
        $handler = fopen( $filename, "w" );
        fwrite( $handler, "<?php\n\nreturn [\n" );
    } else $handler = $openfile;

    foreach ( $data as $name => $value ) {
        $name = addcslashes( $name, "'" );

        if ( is_array( $value ) ) {
            fwrite( $handler, "{$prefix}'{$name}' => [\n" );
            save_con( $filename, $value, $handler, $prefix . "\t" );
            fwrite( $handler, "{$prefix}],\n" );
        } else {
            $value = addcslashes( $value, "'" );
            fwrite( $handler, "{$prefix}'{$name}' => '{$value}',\n" );
        }
    }

    if ( !$openfile ) {
        fwrite( $handler, "];\n\n?>" );
        fclose( $handler );
    }
}

function ShowItem($name, $title, $descr, $value = '', $type = 'text', $values = []) {
    if ( count($values) ) {
        if ( !is_array($value) ) $value = $value ? [$value] : [];
    }
    if ( empty($values) && $name ) $value = htmlspecialchars($value);
    if ( $name == '' ) $type = '__html__';

    $options = [];

    foreach ($values as $option_value => $option_name) {
        $options[] = '<option value="' . $option_value . '"' . (in_array($option_value, $value, true) ? ' selected' : '') . '>' . $option_name . '</option>';
    }

    switch ($type) {
        case 'text':
            $field = '<input type="text" class="form-control" name="' . $name . '" value="' . $value . '">';
            break;

        case 'number':
            $field = '<input type="number" class="form-control" name="' . $name . '" value="' . intval($value) . '">';
            break;

        case 'checkbox':
            $field = '<input type="checkbox" class="switch" name="' . $name . '" value="1"' . ($value ? ' checked' : '') . '>';
            break;

        case 'textarea':
            $field = '<textarea class="form-control" name="' . $name . '">' . $value . '</textarea>';
            break;

        case 'multiselect':
            $field = '<select name="' . $name . '" title=" " data-placeholder=" " multiple>' . implode('', $options) .  '</select>';
            break;

        case 'select':
            $field = '<select name="' . $name . '" class="uniform">' . implode('', $options) .  '</select>';
            break;

        case '__html__':
            $field = $value;
            break;

        default:
            $field = '';
            break;
    }

    return '<tr><td class="col-xs-4 col-sm-4 col-md-4"><h6 class="media-heading text-semibold">' . $title . '</h6><span class="text-muted text-size-small hidden-xs">' . $descr . '</span></td><td class="col-xs-8 col-sm-8 col-md-8">' . $field . '</td></tr>';
}

function get_full_link($row) {
    global $config;

    if ( $config['allow_alt_url'] ) {
        if ( $config['seo_type'] == 1 OR $config['seo_type'] == 2  ) {
            if ( $row['category'] and $config['seo_type'] == 2 ) {
                $full_link = $config['http_home_url'] . get_url( $row['category'] ) . "/" . $row['id'] . "-" . $row['alt_name'] . ".html";
            } else $full_link = $config['http_home_url'] . $row['id'] . "-" . $row['alt_name'] . ".html";
        } else $full_link = $config['http_home_url'] . date( 'Y/m/d/', $row['post_date'] ) . $row['alt_name'] . ".html";
    } else $full_link = $config['http_home_url'] . "index.php?newsid=" . $row['id'];

    return $full_link;
}

$__name = '<i class="fa fa-book"></i> DLE Files';
$__descr = 'Просмотр списка загруженных на сайт файлов';

$files_order_options = [
    'ABS(f.date) DESC' => 'По дате загрузки (по убыв.)',
    'ABS(f.date) ASC' => 'По дате загрузки (по возр.)',
    'ABS(f.size) DESC' => 'По размеру (по убыв.)',
    'ABS(f.size) ASC' => 'По размеру (по возр.)'
];
$files_order_select = '';

$files_order = (!empty($_REQUEST['files_order'])) ? $_REQUEST['files_order'] : False;
if ( !isset($files_order_options[$files_order]) ) $files_order = 'ABS(f.date) DESC';

foreach ($files_order_options as $value => $label) {
    $files_order_select .= '<option value="' . $value . '"' . ($value == $files_order ? ' selected' : '') . '>' . $label . '</option>';
}

$files_limit = (!empty($_REQUEST['files_limit'])) ? intval($_REQUEST['files_limit']) : 50;
if ( $files_limit < 0 ) $files_limit = 50;

$start_from = (!empty($_REQUEST['start_from'])) ? intval($_REQUEST['start_from']) : 0;
if ( $start_from < 0 ) $start_from = 0;

$xfields = xfieldsload();

$content = '';

$prefix = PREFIX;

$sql_select = <<<SQL
SELECT f.*, p.title, p.xfields, p.alt_name, p.date as post_date, p.category
FROM {$prefix}_files f
LEFT JOIN {$prefix}_post p
    ON f.news_id = p.id
WHERE f.news_id > 0 AND NOT p.title IS NULL
ORDER BY {$files_order}
LIMIT {$start_from},{$files_limit}
SQL;

$sql_count = <<<SQL
SELECT COUNT(*) as count
FROM {$prefix}_files f
LEFT JOIN {$prefix}_post p
    ON f.news_id = p.id
WHERE f.news_id > 0 AND NOT p.title IS NULL
SQL;
$all_count_news = $db->super_query($sql_count);
$all_count_news = $all_count_news['count'];

$q = $db->query($sql_select);

while($row = $db->get_row($q)) {
    $row['title'] = stripslashes($row['title']);
    $row['xfields'] = stripslashes($row['xfields']);
    $row['xfields'] = xfieldsdataload($row['xfields']);
    $row['post_date'] = strtotime($row['post_date']);

    $file_xfield = '—';

    foreach ($xfields as $xfield) {
        if ( $xfield[3] !== 'file' ) continue;

        if ( empty($row['xfields'][$xfield[0]]) ) continue;

        if ( stripos($row['xfields'][$xfield[0]], '=' . $row['id'] . ']') !== False
            || stripos($row['xfields'][$xfield[0]], '=' . $row['id'] . ':') !== False ) {
            $file_xfield = $xfield[1];
            break;
        }
    }

    $author_link = $config['http_home_url'] . 'user/' . urlencode($row['author']) . '/';
    $author_link_edit = $config['http_home_url'] . 'admin.php?mod=editusers&action=edituser&user=' . $row['author'];
    $file_link = $config['http_home_url'] . 'index.php?do=download&id=' . $row['id'];
    $news_link = get_full_link($row);
    $news_link_edit = $config['http_home_url'] . $config['admin_path'] . '?mod=editnews&action=editnews&id=' . $row['news_id'];
    $upload_date = date('Y-m-d H:i:s', $row['date']);
    $file_size = formatsize($row['size']);

    $content .= <<<HTML
<tr>
    <td style="white-space: nowrap;">{$upload_date}</td>
    <td style="white-space: nowrap;">
        <a href="{$author_link}" target="_blank" title="Перейти на страницу пользователя на сайте">{$row['author']}</a> &nbsp;
        <a href="{$author_link_edit}" target="_blank" title="Перейти в редактирование пользователя"><i class="fa fa-edit"></i></a>
    </td>
    <td>
        <a href="{$file_link}" title="Скачать файл на компьютер">{$row['name']}</a>
    </td>
    <td style="white-space: nowrap;">{$file_size}</td>
    <td>{$row['dcount']}</td>
    <td style="white-space: nowrap;">
        <a href="{$news_link}" target="_blank"title="{$row['title']}">{$row['news_id']}</a> &nbsp;
        <a href="{$news_link_edit}" target="_blank"title="Перейти в редактирование новости"><i class="fa fa-edit"></i></a>
    </td>
    <td style="white-space: nowrap;">{$file_xfield}</td>
</tr>
HTML;
}

if ( $content == '' ) {
    $content = '<tr><td colspan="7" style="text-align: center;padding: 25px;">Загруженных файлов не найдено</td></tr>';
}

// pagination
$npp_nav = '';

if( $all_count_news > $files_limit ) {
    if( $start_from > 0 ) {
        $previous = $start_from - $files_limit;
        $npp_nav .= "<li><a onclick=\"javascript:search_submit($previous); return(false);\" href=\"#\" title=\"{$lang['edit_prev']}\"><i class=\"fa fa-backward\"></i></a></li>";
    }

    $enpages_count = @ceil( $all_count_news / $files_limit );
    $enpages_start_from = 0;
    $enpages = "";

    if( $enpages_count <= 10 ) {

        for($j = 1; $j <= $enpages_count; $j ++) {

            if( $enpages_start_from != $start_from ) {

                $enpages .= "<li><a onclick=\"javascript:search_submit($enpages_start_from); return(false);\" href=\"#\">$j</a></li>";

            } else {

                $enpages .= "<li class=\"active\"><span>$j</span></li>";
            }

            $enpages_start_from += $files_limit;
        }

        $npp_nav .= $enpages;

    } else {

        $start = 1;
        $end = 10;

        if( $start_from > 0 ) {

            if( ($start_from / $files_limit) > 4 ) {

                $start = @ceil( $start_from / $files_limit ) - 3;
                $end = $start + 9;

                if( $end > $enpages_count ) {
                    $start = $enpages_count - 10;
                    $end = $enpages_count - 1;
                }

                $enpages_start_from = ($start - 1) * $files_limit;

            }

        }

        if( $start > 2 ) {

            $enpages .= "<li><a onclick=\"javascript:search_submit(0); return(false);\" href=\"#\">1</a></li> <li><span>...</span></li>";

        }

        for($j = $start; $j <= $end; $j ++) {

            if( $enpages_start_from != $start_from ) {

                $enpages .= "<li><a onclick=\"javascript:search_submit($enpages_start_from); return(false);\" href=\"#\">$j</a></li>";

            } else {

                $enpages .= "<li class=\"active\"><span>$j</span></li>";
            }

            $enpages_start_from += $files_limit;
        }

        $enpages_start_from = ($enpages_count - 1) * $files_limit;
        $enpages .= "<li><span>...</span></li><li><a onclick=\"javascript:search_submit($enpages_start_from); return(false);\" href=\"#\">$enpages_count</a></li>";

        $npp_nav .= $enpages;

    }

    if( $all_count_news > $i ) {
        $how_next = $all_count_news - $i;
        if( $how_next > $files_limit ) {
            $how_next = $files_limit;
        }
        $npp_nav .= "<li><a onclick=\"javascript:search_submit($i); return(false);\" href=\"#\" title=\"{$lang['edit_next']}\"><i class=\"fa fa-forward\"></i></a></li>";
    }

    $npp_nav = "<ul class=\"pagination pagination-sm mb-20\">".$npp_nav."</ul>";
}
// pagination

$content = <<<HTML
<div class="panel panel-default">
    <div class="panel-heading">
        Список файлов загруженных на сайт
    </div>

    <form method="post" id="advanced_search" style="display: block;">
        <input type="hidden" name="mod" value="{$mod}">
        <input type="hidden" name="start_from" id="start_from" value="{$start_from}">

        <div class="panel-body" style="border-bottom: 1px solid #ccc;">
            <div class="form-group">
        		<div class="row">
        			<div class="col-sm-6">
        				<label>Сортировка:</label>
        				<select class="uniform" data-width="100%" name="files_order">
        					{$files_order_select}
        				</select>
        			</div>

        			<div class="col-sm-6">
        				<label>Файлов на страницу:</label>
        				<input class="form-control text-center" name="files_limit" value="{$files_limit}" type="text">
        			</div>
        		</div>
        	</div>

            <button onclick="javascript:search_submit(0); return(false);" class="btn bg-teal btn-sm btn-raised position-left"><i class="fa fa-search position-left"></i>Показать</button>
	        <button onclick="document.location='?mod={$mod}'; return(false);" class="btn bg-danger btn-sm btn-raised"><i class="fa fa-eraser position-left"></i>Сбросить поиск</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-xs">
            <thead>
                <tr>
                    <th>Дата загрузки</th>
                    <th>Автор</th>
                    <th>Файл</th>
                    <th>Размер</th>
                    <th>Скачали</th>
                    <th>Новость</th>
                    <th>Доп-поле</th>
                </tr>
            </thead>

            <tbody>
                {$content}
            </tbody>
        </table>
    </div>
</div>

{$npp_nav}

<script type="text/javascript">
function search_submit(start_from) {
    document.querySelector('#start_from').value = start_from;
    document.querySelector('#advanced_search').submit();
    console.log(start_from);
}
</script>
HTML;

echoheader($__name, $__descr);

echo $content;

echo '<div class="panel panel-body" style="opacity: 0.6;text-align: center;">Автор: <a href="http://zerocoolpro.biz/forum/members/icooler.3086/" target="_blank">iCooLER</a> | Telegram: <a href="https://t.me/thecoooler" target="_blank">@thecoooler</div>';

echofooter();

?>
