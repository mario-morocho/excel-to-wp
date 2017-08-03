<?php
/*
 * Plugin Name: Data Importer
 * Plugin URI: http://www.yakindu.ec
 * Author: Mario Morocho
 * Author URI: http://www.mariomorocho.com
 * Description: It lets you import data from CSV Files to Wordpress DataBase
 * Version: 1.0.0
 * License: MIT
 * Text Domain: excel-to-wp
 */
define('UPLOAD_DATA_FILE', trailingslashit( plugin_dir_path( dirname( __FILE__ ) ) ) . 'ykimporter/data/data.csv');

register_activation_hook( __FILE__, 'activate_ykimporter' );

function activate_ykimporter(){
    wp_mkdir_p(UPLOAD_DATA_DIRCTORY);
}

add_action('admin_menu', 'ykimporter_admin_menu');

function ykimporter_admin_menu() {
    add_menu_page('Importar datos', 'Importar datos', 'manage_options', 'ykimporter-import', 'show_page_importer','dashicons-upload');
}

/**
 * Función principal para la gestión de importación de la información
 * 
 * 1. Presenta interfaz de carga
 * 2. Procesa archivo: copia a servidor y obtiene línea de etiquetas
 * 3. Muestra interfaz para emparejar los campos a ser cargados 
 * 4. Carga la información desde el archivo CSV
 */
function show_page_importer() { ?>
    <div class="wrap" >
        <h1 class="wp-heading-inline">Importar datos a WordPress</h1>
    <?php
    //import_to_wp([]);die;
    if($_POST['data']){
        // 4. Carga la información desde el archivo CSV
        import_to_wp($_POST['data']);
    }
    else if($_POST['tags']){
        // 3. Muestra interfaz para emparejar los campos a ser cargados
        $tags = $_POST['tags'];
        import_config_view($tags);
    }
    else if($file = $_FILES['csv_file']){
        // 2. Procesa archivo: copia a servidor y obtiene linea de etiquetas
        process_csv($file);
    }
    else{
        // 1. Presenta interfaz de carga
        ?>
        <h2>1. Cargar Archivo</h2>
        <p>Es necesario que se cargue un archivo CSV cuya primera fila sean los nombres de las columnas.</p>
        <form  method="post" enctype="multipart/form-data">
            <input type='file' id='test_upload_pdf' name='csv_file'></input>
            <?php submit_button('Cargar') ?>
        </form>
        <?php
    }
    ?> 
    </div>
    <?php
}

/**
 * Carga de archivo
 * @param Redirect 
 */
function process_csv($file){
    //@todo COntrol that file is UTF-8
    if($file['error'] > 0){
        echo '<p><b>Error al cargar archivo. Código: ' . $file['error'] . '.</b></p>';
    }
    else{
        // Copiar archivo a servidor
        if(move_uploaded_file($file['tmp_name'],UPLOAD_DATA_FILE)){
            if (($gestor = fopen(UPLOAD_DATA_FILE, "r")) !== FALSE) {
                $tags = [];
                while (($rows = fgetcsv($gestor, 20000, "\r")) !== FALSE) {
                    for ($i = 0; $i < count($rows); $i++) {
                        $tags = str_getcsv($rows[$i],';');
                        break;
                    }
                    break;
                }
                fclose($gestor);
            }
        }
        
        // Show Etiquetas
        ?>
<h2>Etiquetas de archivo CSV</h2>
<p>Fila de etiquetas para configurar carga a campos personalizados.</p>
<form action="" method="POST">
    <table class="wp-list-table widefat fixed striped pages">
        <thead>
            <tr>
                <th>Etiquetas</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <ul>
                        <?php foreach ($tags as $k => $t): ?>
                        <li>
                            <?php echo $t; ?>
                            <input type="hidden" name="tags[<?php echo $k; ?>]" value="<?php echo $t; ?>" />
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </td>
                <td><input class="button button-primary button-large" type="submit" value="Continuar" /></td>
            </tr>
        </tbody>
    </table>
</form>
        <?php
    }
}

/**
 * Vista para configurar los campos a ser cargados 
 * @param array $tags Arreglo con identificadores de las columnas del archivo a importar
 */
function import_config_view($tags){
    $postTypes = get_post_types();
    global $wpdb;
    $sql = "SELECT DISTINCT meta_key
			FROM $wpdb->postmeta
			WHERE meta_key NOT BETWEEN '_' AND '_z'
			HAVING meta_key NOT LIKE %s
			ORDER BY meta_key";
    $keys = $wpdb->get_col( $wpdb->prepare( $sql, $wpdb->esc_like( '_' ) . '%' ) );
    $keys = array_merge(['post_title','post_content','post_excerpt'],$keys);
    $dataType = [
        'T' => 'Cadena',
        'D' => 'Fecha',
        'I' => 'Entero',
    ];
    ?>
    <form action="" method="POST">
        <h2>2. Configuraciones</h2>
        <p>Seleccione el tipo de contenido donde se realizará la carga de información.</p>
        <table class="wp-list-table widefat fixed striped pages">
            <thead>
                <tr>
                    <th>Tipo de Contenido</th>
                    <th>Clave Principal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo getListOfElements($postTypes,'data[yk_post_type]'); ?></td>
                    <td><?php echo getListOfElements($keys,'data[yk_main_key]',TRUE); ?></td>
                    <td><input class="button button-primary button-large" type="submit" value="Procesar carga" /></td>
                </tr>
            </tbody>
        </table>
        
        <p>Seleccione los campos personalizados donde se cargará la información desde archivo CSV.</p>
        <table class="wp-list-table widefat fixed striped pages">
            <thead>
                <tr>
                    <th>Campo personalizado</th>
                    <th>Campo tomado de archivo</th>
                    <th>Tipo de dato</th>
                    <th>Valor Predeterminado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keys as $k => $v): ?>
                <?php if(!is_protected_meta( $v, 'post' ) ): ?>
                <tr>
                    <td>
                        <?php echo $v ?>
                        <input type="hidden" name="data[field][<?php echo $k; ?>][name]" value="<?php echo $v; ?>" />
                    </td>
                    <td><?php echo getListOfElements($tags, 'data[field][' . $k . '][value]'); ?></td>
                    <td><?php echo getListOfElements($dataType, 'data[field][' . $k . '][dt]',FALSE,FALSE); ?></td>
                    <td><input type="text" name="data[field][<?php echo $k; ?>][default]" value="" /></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    <?php
}

function import_to_wp($data){
    if(!$data['yk_post_type']){
        exit;
    }
    
    // MATCH JUST SELECTED FIELDS
    $key = $dflt = [];
    foreach ($data['field'] as $d){
        if($d['default'] != '' || $d['value'] != '')
            $key[$d['name']] = $d;
    }
    
    // GET FILE 
    if (($gestor = fopen(UPLOAD_DATA_FILE, "r")) !== FALSE) {
        $cont = 0;
        while (($rows = fgetcsv($gestor, 0, "\r")) !== FALSE) {
            for ($i = 0; $i < count($rows); $i++) {
                if($i === 0 && $cont === 0){
                    continue;
                }
                $row = str_getcsv($rows[$i],';');
                
                // Create post object
                $obj = ['post_title' => '', 'post_content' => '', 'post_excerpt' => ''];
                
                if(isset($key['post_title'])){
                    $obj['post_title'] = $key['post_title']['default'] ? $key['post_title']['default'] : $row[$key['post_title']['value']];
                }                    
                
                if(isset($key['post_content'])){
                    $obj['post_content'] = $key['post_content']['default'] ? $key['post_content']['default'] : $row[$key['post_content']['value']];
                }
                
                if(isset($key['post_excerpt'])){
                    $obj['post_excerpt'] = $key['post_excerpt']['default'] ? $key['post_excerpt']['default'] : $row[$key['post_excerpt']['value']];
                }
                
                // CHEK IF POST EXIST
                
                // INSERT POST
                $id = wp_insert_post([
                    'post_title'    => $obj['post_title'],
                    'post_content'  => $obj['post_content'],
                    'post_excerpt'  => $obj['post_excerpt'],
                    'post_date'     => date('Y-m-d'),
                    'post_type'     => $data['yk_post_type'],
                    'post_status'   => 'publish',
                ]);
                
                if($id){
                    // INSERT META
                    echo '<h3>Post ' . $obj['post_title'] . ' creado: ' . $id .'</h3>';
                    foreach($key as $k => $d){
                        if(!in_array($k, ['post_title','post_content','post_excerpt'])){
                            $val = $d['default'] ? $d['default'] : $row[$d['value']];
                            if($d['dt'] == 'D'){
                                $val = date('Ymd', strtotime($val));
                            }
                            if(!add_post_meta($id, $k, $val)){
                                echo '<p>Error en MEDATA ' . $k . ' con valor ' . $val . '</p>';
                            }
                        }
                    }
                }
            }
            $cont++;
        }
        fclose($gestor);
    }
    die;
}
/**
 * This function creates select HTML element
 * 
 * @param type $elements        Array of elements to create select html view
 * @param type $name            Name of the element
 * @param type $valueIsKey      A flag to set value as the name shown and the value of option tag
 * @param type $selectorInfo    A flag to set if first element of select is info
 * @return string               HTML with de select element
 */
function getListOfElements($elements,$name,$valueIsKey = false, $selectorInfo = true){
    $list = '<select name="' . $name . '">';
    $list .= $selectorInfo ? '<option value="">Seleccione uno</option>' : '';
    foreach($elements as $k => $t){
        $key = $valueIsKey ? $t : $k;
        $list .= '<option value="' . $key . '">' . $t . '</option>';
    }
    $list .= '</select>';
    return $list;
}

