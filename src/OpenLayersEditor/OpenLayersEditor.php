<?php

namespace MarceloNees\Plugins\OpenLayersEditor;

use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Base\TStyle;
use Exception;

/**
 * OpenLayersEditor - Componente para edição de geometrias
 * 
 * @author Marcelo Barreto Nees <marcelo.linux@gmail.com>
 * @version 1.3 - Arquivos javascript e css separados
 * @package MarceloNees\Plugins\OpenLayersEditor
 */
class OpenLayersEditor extends TElement
{
    private $id;
    private $width = '100%';
    private $height = '500px';
    private $center = [-49.0904928, -26.504104];
    private $zoom = 15;
    private $geom = null;
    private $options = [];
    private $mapContainerId;
    private $layers = [];
    private $editorConfigFieldId = null;
    private $geometryFieldId = null;
    private $restoreConfigData = null;

    /**
     * Class Constructor
     */
    public function __construct($options = [])
    {
        parent::__construct('div');

        $this->id = 'ol_editor_' . uniqid();
        $this->mapContainerId = 'ol_map_' . uniqid();

        $defaultAssetsPath = 'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/';

        $defaultButtons = [
            'select' => ['icon' => 'fa-mouse-pointer', 'label' => 'Selecionar', 'hint' => 'Selecionar geometria'],
            'draw'   => ['icon' => 'fa-pencil', 'label' => 'Desenhar', 'hint' => 'Desenhar nova geometria'],
            'modify' => ['icon' => 'fa-edit', 'label' => 'Modificar', 'hint' => 'Modificar geometria existente'],
            'undo'   => ['icon' => 'fa-undo', 'label' => 'Voltar', 'hint' => 'Desfazer última ação (Ctrl+Z)'],
            'redo'   => ['icon' => 'fa-redo', 'label' => 'Refazer', 'hint' => 'Refazer ação desfeita (Ctrl+Y)']
        ];

        $defaultLayers = [
            'osm' => [
                'type' => 'tile',
                'source' => 'osm',
                'opacity' => 0.3,
                'visible' => true,
                'title' => 'OpenStreetMap'
            ]
        ];

        $this->options = array_merge([
            'showToolbar' => true,
            'toolbarButtons' => $defaultButtons,
            'assetsPath' => $defaultAssetsPath,
            'center' => [-49.0904928, -26.504104],
            'zoom' => 15,
            'freehand' => false,
            'showLayerControl' => true,
            'layers' => $defaultLayers,
            'showToolbarLabels' => true,
            'toolbarPosition' => 'top-right',
            'editorConfigField' => null,
            'geometryField' => null,
            'restoreConfig' => null
        ], $options);

        $this->center = $this->options['center'];
        $this->zoom = $this->options['zoom'];
        $this->layers = $this->options['layers'];
        $this->editorConfigFieldId = $this->options['editorConfigField'];
        $this->geometryFieldId = $this->options['geometryField'];
        $this->restoreConfigData = $this->options['restoreConfig'];
    }

    /**
     * Set map dimensions
     */
    public function setSize($width, $height)
    {
        $this->width = is_numeric($width) ? "{$width}px" : $width;
        $this->height = is_numeric($height) ? "{$height}px" : $height;

        $style = new TElement('style');
        $style->add('#' . $this->id . '{ height:' . $this->height . ';  width: ' . $this->width . '; }');

        parent::add($style);
        return $this;
    }

    /**
     * setWidth
     */
    public function setWidth($width = '100px')
    {
        $this->width = $width;

        $style = new TElement('style');
        $style->add('#' . $this->id . '{ height:' . $this->height . ';  width: ' . $this->width . '; }');

        parent::add($style);
        return $this;
    }

    /**
     * setHeight
     */
    public function setHeight($height = '100px')
    {
        $this->height = $height;

        $style = new TElement('style');
        $style->add('#' . $this->id . '{ height:' . $this->height . ';  width: ' . $this->width . '; }');

        parent::add($style);
        return $this;
    }

    public function setGeometry($geom)
    {
        $this->geom = $geom;
        return $this;
    }

    public function setCenter($lat, $lng)
    {
        $this->center = [$lng, $lat];
        return $this;
    }

    public function setZoom($zoom)
    {
        $this->zoom = $zoom;
        return $this;
    }

    public function addLayer($name, $config)
    {
        $this->layers[$name] = $config;
        return $this;
    }

    public function removeLayer($name)
    {
        if (isset($this->layers[$name])) {
            unset($this->layers[$name]);
        }
        return $this;
    }

    public function setLayers($layers)
    {
        $this->layers = $layers;
        return $this;
    }

    public function setFreehand($enabled)
    {
        $this->options['freehand'] = (bool) $enabled;
        return $this;
    }

    public function setShowToolbarLabels($showLabels)
    {
        $this->options['showToolbarLabels'] = (bool) $showLabels;
        return $this;
    }

    public function setToolbarPosition($position)
    {
        $positions = ['top-right', 'top-left', 'bottom-right', 'bottom-left'];
        if (in_array($position, $positions)) {
            $this->options['toolbarPosition'] = $position;
        }
        return $this;
    }

    public function setEditorConfigField($fieldId)
    {
        $this->editorConfigFieldId = $fieldId;
        $this->options['editorConfigField'] = $fieldId;
        return $this;
    }

    public function setGeometryField($fieldId)
    {
        $this->geometryFieldId = $fieldId;
        $this->options['geometryField'] = $fieldId;
        return $this;
    }

    public function setRestoreConfig($configData)
    {
        if (is_array($configData)) {
            $this->restoreConfigData = json_encode($configData);
        } else {
            $this->restoreConfigData = $configData;
        }
        $this->options['restoreConfig'] = $this->restoreConfigData;
        return $this;
    }

    public function restoreConfig($configData = null, $delay = 1000)
    {
        if ($configData !== null) {
            $this->setRestoreConfig($configData);
        }

        if ($this->restoreConfigData) {
            $configJson = is_string($this->restoreConfigData) ?
                $this->restoreConfigData :
                json_encode($this->restoreConfigData);

            TScript::create("
                setTimeout(function() {
                    if (typeof GeoMapEditorApp !== 'undefined' && GeoMapEditorApp.restoreConfig) {
                        console.log('🔄 Restaurando configurações via método PHP...');
                        GeoMapEditorApp.restoreConfig({$configJson});
                    } else {
                        console.warn('⚠️ GeoMapEditorApp não disponível para restaurar');
                    }
                }, {$delay});
            ");
        }
    }

    public static function createWithRestore($options, $configData)
    {
        $editor = new self($options);
        $editor->setRestoreConfig($configData);
        return $editor;
    }

    /**
     * Create the map
     */
    public function createEditor()
    {
        // Verifica se os arquivos necessários existem
        $requiredFiles = [
            'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/ol.js',
            'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/ol-editor.js'
        ];

        foreach ($requiredFiles as $file) {
            if (!file_exists($file)) {
                throw new Exception("Arquivo necessário não encontrado: {$file}");
            }
        }

        $requiredVersions = [
            'ol' => '6.5.0'        // Versão do OpenLayers
        ];

        // Adicione esta função para verificar versões
        $versionCheckJS = "
            function checkEditorLibraryVersions() {
                const errors = [];
                const versions = {};
            
                /* Verifica OpenLayers */
                if (typeof ol !== 'undefined') {
                    versions.ol = ol.getVersion ? ol.getVersion() : 'indeterminada';
                    console.warn('Versão do OpenLayers:', versions.ol);
                    if (versions.ol < '{$requiredVersions['ol']}') {
                        errors.push(`Versão do OpenLayers é menor que a requerida ({$requiredVersions['ol']})`);
                    }
                } else {
                    errors.push('OpenLayers não carregado');
                }
            
                console.log('Versões carregadas:', versions);
                if (errors.length > 0) {
                    console.error('Problemas de versão:', errors);
                    return false;
                }
                return true;
            }
        ";

        $containerId = $this->mapContainerId;
        $geometryFieldId = $this->geometryFieldId ? $this->geometryFieldId : '';
        $editorConfigFieldId = $this->editorConfigFieldId ? $this->editorConfigFieldId : '';

        $centerJson = json_encode($this->center);
        $layersJson = json_encode($this->layers);
        $geomJson = $this->geom ? json_encode($this->geom) : 'null';
        $zoom = (int) $this->zoom;
        $freehand = $this->options['freehand'] ? 'true' : 'false';
        $showToolbar = $this->options['showToolbar'] ? 'true' : 'false';
        $showToolbarLabels = $this->options['showToolbarLabels'] ? 'true' : 'false';
        $toolbarPosition = $this->options['toolbarPosition'];
        $showLayerControl = $this->options['showLayerControl'] ? 'true' : 'false';
        $toolbarButtons = json_encode($this->options['toolbarButtons']);
        $restoreConfigData = $this->restoreConfigData ? json_encode($this->restoreConfigData) : 'null';

        /* Garante que o CSS seja carregado primeiro */
        TStyle::importFromFile('vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/ol.css');
        TStyle::importFromFile('vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/ol-editor.css');

        /* Font Awesome */
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">';

        /* Cria um sistema de carregamento robusto com verificações de segurança */
        TScript::create("
        /* Função para verificar segurança antes de acessar coordenadas */
        function safeGetCoordinates(geoObj) {
            try {
                if (!geoObj || !geoObj.geometry || !geoObj.geometry.coordinates) {
                    console.warn('Objeto geoespacial inválido:', geoObj);
                    return null;
                }
                return geoObj.geometry.coordinates;
            } catch(e) {
                console.error('Erro ao acessar coordenadas:', e);
                return null;
            }
        }
        
        /* Função principal de inicialização */
        function initializeEditor() {
            try {
                /* Verificação de segurança */
                if (typeof GeoMapEditorApp === 'undefined') {
                    throw new Error('GeoMapEditorApp não está definido');
                }
                
                /* Configuração do editor */
                var config = {
                    containerId: '{$containerId}',
                    geometryFieldId: '{$geometryFieldId}',
                    editorConfigFieldId: '{$editorConfigFieldId}',
                    center: {$centerJson},
                    zoom: {$zoom},
                    freehand: {$freehand},
                    showToolbar: {$showToolbar},
                    showToolbarLabels: {$showToolbarLabels},
                    toolbarPosition: '{$toolbarPosition}',
                    showLayerControl: {$showLayerControl},
                    toolbarButtons: {$toolbarButtons},
                    layers: {$layersJson},
                    geom: {$geomJson}
                };
                
                /* Adicionar restoreConfig se existir */
                if ({$restoreConfigData} !== null) {
                    config.restoreConfig = {$restoreConfigData};
                }
                
                console.log('📝 Configurações do editor:', config);
                
                /* Inicializa o editor */
                GeoMapEditorApp.init(config);
                
                console.log('✅ Editor inicializado com sucesso');
            } catch(initError) {
                console.error('Erro na inicialização do editor:', initError);
            }
        }
        
        /* Carrega os scripts necessários em ordem */
        var requiredScripts = [
            'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/ol.js',
            'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/ol-editor.js'
        ];
        
        /* Função para carregar scripts sequencialmente */
        function loadScript(scripts, callback) {
            if (scripts.length === 0) {
                callback();
                return;
            }
            
            var currentScript = scripts.shift();
            $.getScript(currentScript)
                .done(function() {
                    console.log('✅ Script carregado:', currentScript);
                    loadScript(scripts, callback);
                })
                .fail(function() {
                    console.error('❌ Falha ao carregar:', currentScript);
                    loadScript(scripts, callback);
                });
        }
        
        /* Inicia o processo de carregamento */
        loadScript(requiredScripts, function() {
            /* Verifica se todos os requisitos estão carregados */
            if (typeof ol === 'undefined' || typeof GeoMapEditorApp === 'undefined') {
                console.error('Bibliotecas necessárias não carregadas');
                return;
            }
            
            /* Executa a inicialização */
            setTimeout(initializeEditor, 100);
        });

        /* Após carregar tudo, verifique as versões */
        setTimeout(function() {
            {$versionCheckJS}
        }, 500);        
        ");
    }

    /**
     * Show the editor
     */
    public function show()
    {
        // Set container dimensions
        $style = new TStyle("#{$this->id}");
        $style->width = $this->width;
        $style->height = $this->height;
        $style->border = '0px';
        $style->background = '#f0f0f0';
        $style->position = 'relative';
        $style->overflow = 'hidden';
        $style->display = 'block';
        $style->zIndex = '1';
        $style->show();

        // Create editor container
        $content = new TElement('div');
        $content->id = $this->mapContainerId;
        $content->class = 'ol-editor-map-container';
        $content->style = 'width: 100%; height: 100%; position: absolute; top: 0; left: 0;';
        $content->add('Carregando mapa...');

        parent::add($content);
        $this->createEditor();
        parent::show();
    }
}
