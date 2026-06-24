<?php

namespace MarceloNees\Plugins\OpenLayersEditor;

use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Base\TStyle;

/**
 * OpenLayersEditor - Componente para edição de geometrias
 * 
 * @author Marcelo Barreto Nees <marcelo.linux@gmail.com>
 * @version 1.2 - MÉTODO PHP PARA RESTAURAR CONFIGURAÇÕES
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
    private $restoreConfigData = null; // Armazena dados para restauração

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
            ],
            'ortomosaico' => [
                'type' => 'xyz',
                'url' => 'https://www.jaraguadosul.sc.gov.br/geo/ortomosaico2020/{z}/{x}/{y}.png',
                'maxZoom' => 19,
                'opacity' => 1.0,
                'visible' => true,
                'title' => 'Ortomosaico 2020'
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
            'restoreConfig' => null // Dados de configuração para restaurar
        ], $options);

        $this->center = $this->options['center'];
        $this->zoom = $this->options['zoom'];
        $this->layers = $this->options['layers'];
        $this->editorConfigFieldId = $this->options['editorConfigField'];
        $this->geometryFieldId = $this->options['geometryField'];
        $this->restoreConfigData = $this->options['restoreConfig'];
    }

    public function setSize($width, $height)
    {
        $this->width = is_numeric($width) ? "{$width}px" : $width;
        $this->height = is_numeric($height) ? "{$height}px" : $height;
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

    /**
     * Define o campo que receberá as configurações completas do editor
     * 
     * @param string $fieldId ID do campo no formulário
     * @return self
     */
    public function setEditorConfigField($fieldId)
    {
        $this->editorConfigFieldId = $fieldId;
        $this->options['editorConfigField'] = $fieldId;
        return $this;
    }

    /**
     * Define o campo que receberá a geometria
     * 
     * @param string $fieldId ID do campo no formulário
     * @return self
     */
    public function setGeometryField($fieldId)
    {
        $this->geometryFieldId = $fieldId;
        $this->options['geometryField'] = $fieldId;
        return $this;
    }

    /**
     * Define os dados de configuração para restaurar
     * 
     * @param string|array $configData Dados de configuração (JSON string ou array)
     * @return self
     */
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

    /**
     * Método PHP para restaurar as configurações do editor
     * Este método deve ser chamado após a criação do editor
     * 
     * @param string|array $configData Dados de configuração (JSON string ou array)
     * @param int $delay Delay em milissegundos antes de restaurar (padrão: 1000)
     * @return void
     */
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
                    if (typeof restoreEditorConfig === 'function') {
                        console.log('🔄 Restaurando configurações via método PHP...');
                        restoreEditorConfig({$configJson});
                    } else {
                        console.warn('⚠️ Função restoreEditorConfig não disponível');
                    }
                }, {$delay});
            ");
        }
    }

    /**
     * Método estático para criar uma instância com restauração automática
     * 
     * @param array $options Opções do editor
     * @param string|array $configData Dados de configuração para restaurar
     * @return self
     */
    public static function createWithRestore($options, $configData)
    {
        $editor = new self($options);
        $editor->setRestoreConfig($configData);
        return $editor;
    }

    public function show()
    {
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

        $mapContainer = new TElement('div');
        $mapContainer->id = $this->mapContainerId;
        $mapContainer->style = 'width: 100%; height: 100%; position: absolute; top: 0; left: 0;';
        $mapContainer->add('Carregando mapa...');

        $this->add($mapContainer);

        $this->loadAssets();
        $this->initMap();

        /* Se houver dados de restauração, restaurar após o mapa carregar */
        if ($this->restoreConfigData) {
            $this->restoreConfig(null, 1500);
        }

        parent::show();
    }

    private function loadAssets()
    {
        $basePath = $this->options['assetsPath'];
        TStyle::importFromFile($basePath . 'ol.css');

        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">';
    }

    private function initMap()
    {
        $containerId = $this->mapContainerId;
        $assetsPath = $this->options['assetsPath'];

        $centerJson = json_encode($this->center);
        $layersJson = json_encode($this->layers);
        $geomJson = $this->geom ? json_encode($this->geom) : 'null';
        $zoom = (int) $this->zoom;
        $freehand = $this->options['freehand'] ? 'true' : 'false';
        $showLayerControl = $this->options['showLayerControl'] ? 'true' : 'false';
        $showToolbarLabels = $this->options['showToolbarLabels'] ? 'true' : 'false';
        $toolbarPosition = $this->options['toolbarPosition'];
        $editorConfigFieldId = $this->editorConfigFieldId ? $this->editorConfigFieldId : '';
        $geometryFieldId = $this->geometryFieldId ? $this->geometryFieldId : '';

        $showToolbar = $this->options['showToolbar'] ? 'true' : 'false';
        $toolbarButtons = json_encode($this->options['toolbarButtons']);

        $positionStyles = [
            'top-right' => 'top:10px;right:10px;bottom:auto;left:auto;',
            'top-left' => 'top:10px;left:10px;bottom:auto;right:auto;',
            'bottom-right' => 'bottom:10px;right:10px;top:auto;left:auto;',
            'bottom-left' => 'bottom:10px;left:10px;top:auto;right:auto;'
        ];
        $toolbarPositionStyle = $positionStyles[$toolbarPosition] ?? $positionStyles['top-right'];

        TScript::create("
            (function() {
                console.log('=== OpenLayersEditor - MÉTODO PHP PARA RESTAURAR ===');
                console.log('Container ID: ' + '{$containerId}');
                console.log('Campo geometry: ' + ('{$geometryFieldId}' || 'nenhum'));
                console.log('Campo editor config: ' + ('{$editorConfigFieldId}' || 'nenhum'));
                
                /* ======================================== */
                /* VARIÁVEIS GLOBAIS                       */
                /* ======================================== */
                var _source = null;
                var _history = [];
                var _historyIndex = -1;
                var _maxHistory = 100;
                var _modify = null;
                var _select = null;
                var _draw = null;
                var _map = null;
                var _layer = null;
                var _translate = null;
                var _snap = null;
                var _isUndoRedo = false;
                var _currentDrawType = 'Polygon';
                var _drawSubmenuOpen = false;
                var _freehandEnabled = {$freehand};
                var _originalCursor = null;
                var _isDrawingPoint = false;
                var _layerControlVisible = true;
                var _olLayers = [];
                var _layerConfigs = {$layersJson};
                var _isLayerControlCollapsed = false;
                var _showToolbarLabels = {$showToolbarLabels};
                var _toolbarPosition = '{$toolbarPosition}';
                var _editorConfigFieldId = '{$editorConfigFieldId}';
                var _geometryFieldId = '{$geometryFieldId}';
                
                /* ======================================== */
                /* ESTRUTURA DE CONFIGURAÇÃO DO EDITOR     */
                /* ======================================== */
                var _editorConfig = {
                    layers: {},
                    tools: {
                        activeTool: null,
                        drawType: null,
                        freehand: false
                    },
                    ui: {
                        showToolbarLabels: _showToolbarLabels,
                        toolbarPosition: _toolbarPosition,
                        layerControlCollapsed: false
                    },
                    map: {
                        center: null,
                        zoom: null
                    }
                };
                
                /* ======================================== */
                /* FUNÇÕES DE ATUALIZAÇÃO DE CAMPOS        */
                /* ======================================== */
                function updateGeometryField(value) {
                    if (!_geometryFieldId) return;
                    
                    var field = document.getElementById(_geometryFieldId);
                    if (field) {
                        field.value = value;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('✅ Campo geometria atualizado');
                    }
                }
                
                function updateEditorConfigField() {
                    if (!_editorConfigFieldId) {
                        return;
                    }
                    
                    console.log('📝 updateEditorConfigField');
                    
                    /* Atualizar configurações das camadas */
                    var layerNames = Object.keys(_layerConfigs);
                    layerNames.forEach(function(name, index) {
                        var olLayer = _olLayers[index];
                        if (olLayer) {
                            _editorConfig.layers[name] = {
                                visible: olLayer.getVisible(),
                                opacity: olLayer.getOpacity()
                            };
                        }
                    });
                    
                    /* Atualizar configurações do mapa */
                    if (_map) {
                        var view = _map.getView();
                        _editorConfig.map.center = view.getCenter();
                        _editorConfig.map.zoom = view.getZoom();
                    }
                    
                    /* Atualizar configurações da UI */
                    _editorConfig.ui.layerControlCollapsed = _isLayerControlCollapsed;
                    
                    var configJson = JSON.stringify(_editorConfig);
                    console.log('  Configurações:', configJson);
                    
                    var field = document.getElementById(_editorConfigFieldId);
                    if (field) {
                        field.value = configJson;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log('✅ Configurações atualizadas no campo ' + _editorConfigFieldId);
                    }
                }
                window.updateEditorConfigField = updateEditorConfigField;
                
                /* ======================================== */
                /* FUNÇÃO PARA RESTAURAR CONFIGURAÇÕES     */
                /* ======================================== */
                function restoreEditorConfig(configData) {
                    if (!configData) {
                        console.log('⚠️ Nenhum dado de configuração para restaurar');
                        return;
                    }
                    
                    console.log('📌 Restaurando configurações do editor');
                    
                    try {
                        var config = typeof configData === 'string' ? JSON.parse(configData) : configData;
                        
                        /* Restaurar camadas */
                        if (config.layers) {
                            Object.keys(config.layers).forEach(function(name) {
                                var settings = config.layers[name];
                                var index = Object.keys(_layerConfigs).indexOf(name);
                                
                                if (index !== -1 && _olLayers[index]) {
                                    var olLayer = _olLayers[index];
                                    if (settings.visible !== undefined) {
                                        olLayer.setVisible(settings.visible);
                                        var checkbox = document.getElementById('layer_chk_' + name);
                                        if (checkbox) {
                                            checkbox.checked = settings.visible;
                                        }
                                    }
                                    if (settings.opacity !== undefined) {
                                        olLayer.setOpacity(settings.opacity);
                                        var slider = document.getElementById('layer_opacity_' + name);
                                        if (slider) {
                                            slider.value = Math.round(settings.opacity * 100);
                                            var label = slider.parentNode.querySelector('span');
                                            if (label) {
                                                label.textContent = Math.round(settings.opacity * 100) + '%';
                                            }
                                        }
                                    }
                                }
                            });
                        }
                        
                        /* Restaurar UI */
                        if (config.ui) {
                            if (config.ui.layerControlCollapsed !== undefined) {
                                _isLayerControlCollapsed = config.ui.layerControlCollapsed;
                                var bodyEl = document.getElementById('layer_control_body');
                                var icon = document.querySelector('#layer_toggle_btn i');
                                if (bodyEl && icon) {
                                    if (_isLayerControlCollapsed) {
                                        bodyEl.style.display = 'none';
                                        icon.className = 'fas fa-chevron-down';
                                    } else {
                                        bodyEl.style.display = 'block';
                                        icon.className = 'fas fa-chevron-up';
                                    }
                                }
                            }
                        }
                        
                        /* Restaurar ferramentas */
                        if (config.tools && config.tools.activeTool) {
                            var toolName = config.tools.activeTool;
                            console.log('  🔧 Restaurando ferramenta: ' + toolName);
                            /* A ativação da ferramenta será feita via evento */
                            var event = new CustomEvent('restoreTool', {
                                detail: { tool: toolName }
                            });
                            document.dispatchEvent(event);
                        }
                        
                        /* Atualizar campo de configurações após restaurar */
                        setTimeout(function() {
                            updateEditorConfigField();
                        }, 100);
                        
                        console.log('✅ Configurações restauradas');
                    } catch(e) {
                        console.error('❌ Erro ao restaurar configurações:', e);
                    }
                }
                window.restoreEditorConfig = restoreEditorConfig;
                
                /* ======================================== */
                /* FUNÇÃO PARA CONVERTER GEOMETRIA PARA SALVAR */
                /* ======================================== */
                function convertGeometryForSave(geometry) {
                    if (!geometry) return null;
                    
                    var type = geometry.getType();
                    console.log('  🔄 Convertendo geometria do tipo: ' + type);
                    
                    if (type === 'GeometryCollection') {
                        var geometries = geometry.getGeometries();
                        if (geometries && geometries.length > 0) {
                            for (var i = 0; i < geometries.length; i++) {
                                var subGeom = geometries[i];
                                if (subGeom) {
                                    console.log('    Extraindo sub-geometria: ' + subGeom.getType());
                                    return convertGeometryForSave(subGeom);
                                }
                            }
                        }
                        console.log('    ⚠️ GeometryCollection vazia');
                        return null;
                    }
                    
                    if (type === 'Circle') {
                        console.log('    🔵 Círculo detectado, convertendo para polígono');
                        try {
                            var center = geometry.getCenter();
                            var radius = geometry.getRadius();
                            var polygon = new ol.geom.Polygon.fromCircle(
                                new ol.geom.Circle(center, radius),
                                64
                            );
                            console.log('    ✅ Círculo convertido para polígono com 64 lados');
                            return polygon;
                        } catch(e) {
                            console.error('    ❌ Erro ao converter círculo:', e);
                            return null;
                        }
                    }
                    
                    return geometry;
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA CONVERTER FEATURES PARA SALVAR */
                /* ======================================== */
                function convertFeaturesForSave(features) {
                    var convertedFeatures = [];
                    
                    features.forEach(function(feature) {
                        var geom = feature.getGeometry();
                        if (!geom) return;
                        
                        var convertedGeom = convertGeometryForSave(geom);
                        if (!convertedGeom) return;
                        
                        var newFeature = new ol.Feature({
                            geometry: convertedGeom
                        });
                        newFeature.setProperties(feature.getProperties());
                        convertedFeatures.push(newFeature);
                    });
                    
                    return convertedFeatures;
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA GERAR GEOJSON COMPLETO      */
                /* ======================================== */
                function generateFullGeoJSON(features) {
                    console.log('📦 Gerando GeoJSON completo com CRS');
                    
                    if (!features || features.length === 0) {
                        return null;
                    }
                    
                    try {
                        var convertedFeatures = convertFeaturesForSave(features);
                        
                        if (convertedFeatures.length === 0) {
                            console.warn('⚠️ Nenhuma feature válida após conversão');
                            return null;
                        }
                        
                        var format = new ol.format.GeoJSON();
                        var geojson = format.writeFeatures(convertedFeatures, {
                            dataProjection: 'EPSG:4326',
                            featureProjection: 'EPSG:3857'
                        });
                        
                        var geojsonObj = JSON.parse(geojson);
                        geojsonObj.crs = {
                            type: 'name',
                            properties: {
                                name: 'EPSG:4326'
                            }
                        };
                        
                        var result = JSON.stringify(geojsonObj);
                        console.log('✅ GeoJSON gerado com sucesso, ' + convertedFeatures.length + ' features');
                        return result;
                    } catch(e) {
                        console.error('❌ Erro ao gerar GeoJSON:', e);
                        return null;
                    }
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA ATUALIZAR CAMPO GEOM        */
                /* ======================================== */
                function updateGeometryFieldValue() {
                    console.log('📝 updateGeometryFieldValue');
                    if (!_source) return;
                    
                    var currentFeatures = _source.getFeatures();
                    console.log('  Features atuais:', currentFeatures.length);
                    
                    currentFeatures.forEach(function(feature, index) {
                        var geom = feature.getGeometry();
                        if (geom) {
                            var type = geom.getType();
                            console.log('    Feature ' + index + ' - Tipo: ' + type);
                        }
                    });
                    
                    if (currentFeatures.length > 0) {
                        try {
                            var fullGeojson = generateFullGeoJSON(currentFeatures);
                            
                            if (fullGeojson) {
                                updateGeometryField(fullGeojson);
                                console.log('✅ Geometria atualizada (' + currentFeatures.length + ' features)');
                            } else {
                                console.warn('⚠️ Não foi possível gerar GeoJSON válido');
                            }
                        } catch(e) {
                            console.error('❌ Erro ao atualizar geometria:', e);
                        }
                    } else {
                        updateGeometryField(null);
                        console.log('✅ Geometria removida (vazio)');
                    }
                    
                    /* Atualizar também as configurações do editor */
                    updateEditorConfigField();
                }
                
                /* ======================================== */
                /* SALVAR ESTADO                          */
                /* ======================================== */
                function saveToHistory() {
                    if (!_source) return;
                    if (_isUndoRedo) return;
                    
                    console.log('💾 SALVANDO ESTADO');
                    
                    var features = _source.getFeatures();
                    var data = [];
                    
                    features.forEach(function(feature) {
                        var geom = feature.getGeometry();
                        if (geom) {
                            try {
                                var convertedGeom = convertGeometryForSave(geom);
                                if (convertedGeom) {
                                    var geomJson = new ol.format.GeoJSON().writeGeometry(convertedGeom);
                                    data.push({
                                        geometry: geomJson,
                                        properties: feature.getProperties()
                                    });
                                } else {
                                    console.warn('  ⚠️ Geometria ignorada (conversão falhou)');
                                }
                            } catch(e) {
                                console.warn('Erro ao salvar feature:', e);
                            }
                        }
                    });
                    
                    if (data.length === 0) {
                        console.log('⚠️ Nenhum dado para salvar');
                        return;
                    }
                    
                    if (_historyIndex < _history.length - 1) {
                        _history = _history.slice(0, _historyIndex + 1);
                    }
                    
                    _history.push({
                        features: data,
                        timestamp: Date.now()
                    });
                    
                    if (_history.length > _maxHistory) {
                        _history.shift();
                    }
                    
                    _historyIndex = _history.length - 1;
                    console.log('📝 Histórico salvo (' + _history.length + ' estados)');
                    updateUndoRedoButtons();
                }
                
                /* ======================================== */
                /* RESTAURAR ESTADO                       */
                /* ======================================== */
                function restoreFromHistory(index) {
                    if (index < 0 || index >= _history.length) return;
                    if (!_source || !_map) return;
                    
                    console.log('📌 Restaurando estado ' + (index + 1) + '/' + _history.length);
                    
                    _isUndoRedo = true;
                    var state = _history[index];
                    
                    _source.clear();
                    
                    state.features.forEach(function(item) {
                        try {
                            var geometry = new ol.format.GeoJSON().readGeometry(item.geometry);
                            var feature = new ol.Feature({
                                geometry: geometry
                            });
                            feature.setProperties(item.properties || {});
                            _source.addFeature(feature);
                        } catch(e) {
                            console.warn('Erro ao restaurar feature:', e);
                        }
                    });
                    
                    _historyIndex = index;
                    _source.changed();
                    if (_layer) {
                        _layer.setSource(null);
                        _layer.setSource(_source);
                        _layer.changed();
                    }
                    
                    if (_select) {
                        _map.removeInteraction(_select);
                    }
                    if (_translate) {
                        _map.removeInteraction(_translate);
                    }
                    
                    var selectStyle = new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#ff6600',
                            width: 5
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(255, 102, 0, 0.2)'
                        }),
                        image: new ol.style.Circle({
                            radius: 10,
                            fill: new ol.style.Fill({
                                color: '#ff6600'
                            }),
                            stroke: new ol.style.Stroke({
                                color: '#ffffff',
                                width: 2
                            })
                        })
                    });
                    
                    var newSelect = new ol.interaction.Select({
                        condition: ol.events.condition.click,
                        style: selectStyle,
                        layers: [_layer],
                        multi: false,
                        toggleCondition: ol.events.condition.click
                    });
                    
                    newSelect.on('select', function(event) {
                        if (event.selected.length > 0) {
                            console.log('✅ Geometria selecionada');
                        } else {
                            console.log('🔓 Seleção removida');
                        }
                    });
                    
                    _map.addInteraction(newSelect);
                    _select = newSelect;
                    
                    var newTranslate = new ol.interaction.Translate({
                        features: _select.getFeatures()
                    });
                    _map.addInteraction(newTranslate);
                    _translate = newTranslate;
                    
                    _map.renderSync();
                    _map.updateSize();
                    
                    updateGeometryFieldValue();
                    updateUndoRedoButtons();
                    
                    _isUndoRedo = false;
                    
                    console.log('✅ Estado ' + (index + 1) + '/' + _history.length + ' restaurado');
                    console.log('  Features na camada:', _source.getFeatures().length);
                }
                
                function undo() {
                    if (_historyIndex > 0) {
                        console.log('↩️ Desfazer');
                        restoreFromHistory(_historyIndex - 1);
                    } else {
                        console.log('⚠️ Sem ações para desfazer');
                    }
                }
                
                function redo() {
                    if (_historyIndex < _history.length - 1) {
                        console.log('↪️ Refazer');
                        restoreFromHistory(_historyIndex + 1);
                    } else {
                        console.log('⚠️ Sem ações para refazer');
                    }
                }
                
                function updateUndoRedoButtons() {
                    var undoBtn = document.getElementById('editor_undo_btn');
                    var redoBtn = document.getElementById('editor_redo_btn');
                    
                    if (undoBtn) {
                        undoBtn.disabled = (_historyIndex <= 0);
                        undoBtn.style.opacity = (_historyIndex <= 0) ? '0.5' : '1';
                    }
                    if (redoBtn) {
                        redoBtn.disabled = (_historyIndex >= _history.length - 1);
                        redoBtn.style.opacity = (_historyIndex >= _history.length - 1) ? '0.5' : '1';
                    }
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA ALTERAR O CURSOR            */
                /* ======================================== */
                function setDrawCursor(active) {
                    var container = document.getElementById('{$containerId}');
                    if (!container) return;
                    
                    if (active) {
                        if (!_originalCursor) {
                            _originalCursor = container.style.cursor || 'default';
                        }
                        container.style.cursor = 'crosshair';
                        console.log('✅ Cursor de desenho ativado (crosshair)');
                    } else {
                        if (_originalCursor) {
                            container.style.cursor = _originalCursor;
                        } else {
                            container.style.cursor = 'default';
                        }
                        console.log('✅ Cursor restaurado');
                    }
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA ADICIONAR PONTO MANUALMENTE */
                /* ======================================== */
                function addPointAtCoordinate(coordinate) {
                    console.log('📍 Adicionando ponto em:', coordinate);
                    
                    if (!_source) {
                        console.error('❌ Source não disponível');
                        return false;
                    }
                    
                    try {
                        var point = new ol.geom.Point(coordinate);
                        var feature = new ol.Feature({
                            geometry: point
                        });
                        _source.addFeature(feature);
                        _source.changed();
                        
                        if (_layer) {
                            _layer.changed();
                        }
                        if (_map) {
                            _map.renderSync();
                        }
                        
                        console.log('✅ Ponto adicionado com sucesso');
                        saveToHistory();
                        updateGeometryFieldValue();
                        return true;
                    } catch(e) {
                        console.error('❌ Erro ao adicionar ponto:', e);
                        return false;
                    }
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA CRIAR ESTILO DA CAMADA      */
                /* ======================================== */
                function createLayerStyle() {
                    var baseStyle = new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#00ff00',
                            width: 3
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(0, 255, 0, 0.1)'
                        })
                    });
                    
                    var pointStyle = new ol.style.Style({
                        image: new ol.style.Circle({
                            radius: 8,
                            fill: new ol.style.Fill({
                                color: '#00ff00'
                            }),
                            stroke: new ol.style.Stroke({
                                color: '#ffffff',
                                width: 2
                            })
                        })
                    });
                    
                    return function(feature, resolution) {
                        var geometry = feature.getGeometry();
                        if (!geometry) return baseStyle;
                        
                        var type = geometry.getType();
                        
                        if (type === 'Point' || type === 'MultiPoint') {
                            return pointStyle;
                        }
                        
                        var style = new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: '#00ff00',
                                width: 3
                            }),
                            fill: new ol.style.Fill({
                                color: 'rgba(0, 255, 0, 0.1)'
                            })
                        });
                        
                        if (type === 'Circle') {
                            style = new ol.style.Style({
                                stroke: new ol.style.Stroke({
                                    color: '#00ff00',
                                    width: 3,
                                    lineDash: [5, 5]
                                }),
                                fill: new ol.style.Fill({
                                    color: 'rgba(0, 255, 0, 0.1)'
                                })
                            });
                        }
                        
                        return style;
                    };
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA CRIAR CONTROLE DE CAMADAS   */
                /* ======================================== */
                function createLayerControl(olLayers, layerConfigs) {
                    console.log('🔄 Criando controle de camadas');
                    
                    var container = document.createElement('div');
                    container.id = 'layer_control_container';
                    container.style.cssText = 'position:absolute;bottom:10px;right:10px;z-index:1000;background:white;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.3);min-width:200px;max-height:350px;overflow:hidden;font-family:Arial,sans-serif;font-size:12px;cursor:default;';
                    container.className = 'layer-control-draggable';
                    
                    var header = document.createElement('div');
                    header.id = 'layer_control_header';
                    header.style.cssText = 'padding:8px 12px;background:#f8f9fa;border-bottom:1px solid #dee2e6;font-weight:bold;cursor:move;display:flex;justify-content:space-between;align-items:center;user-select:none;';
                    
                    var leftPart = document.createElement('span');
                    leftPart.style.cssText = 'display:flex;align-items:center;gap:8px;';
                    leftPart.innerHTML = '<span style=\"color:#6c757d;font-size:14px;cursor:grab;\"><i class=\"fas fa-grip-lines\"></i></span><span><i class=\"fas fa-layer-group\"></i> Camadas</span>';
                    header.appendChild(leftPart);
                    
                    var toggleBtn = document.createElement('span');
                    toggleBtn.id = 'layer_toggle_btn';
                    toggleBtn.style.cssText = 'cursor:pointer;padding:0 5px;font-size:14px;';
                    toggleBtn.innerHTML = '<i class=\"fas fa-chevron-up\"></i>';
                    header.appendChild(toggleBtn);
                    
                    container.appendChild(header);
                    
                    var body = document.createElement('div');
                    body.id = 'layer_control_body';
                    body.style.cssText = 'padding:5px 10px;max-height:280px;overflow-y:auto;';
                    container.appendChild(body);
                    
                    var layerNames = Object.keys(layerConfigs);
                    if (layerNames.length === 0) {
                        var emptyMsg = document.createElement('div');
                        emptyMsg.style.cssText = 'padding:10px;color:#6c757d;text-align:center;';
                        emptyMsg.textContent = 'Nenhuma camada configurada';
                        body.appendChild(emptyMsg);
                    } else {
                        layerNames.forEach(function(name, index) {
                            var config = layerConfigs[name];
                            var olLayer = olLayers[index];
                            
                            if (!olLayer) return;
                            
                            var item = document.createElement('div');
                            item.style.cssText = 'padding:4px 0;display:flex;align-items:center;border-bottom:1px solid #f1f3f5;';
                            
                            var checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.checked = (config.visible !== false);
                            checkbox.style.cssText = 'margin-right:8px;cursor:pointer;';
                            checkbox.id = 'layer_chk_' + name;
                            
                            checkbox.onchange = function(e) {
                                var isChecked = this.checked;
                                olLayer.setVisible(isChecked);
                                console.log('🔄 Camada ' + name + ' ' + (isChecked ? 'ativada' : 'desativada'));
                                
                                var opacitySlider = document.getElementById('layer_opacity_' + name);
                                if (opacitySlider) {
                                    opacitySlider.disabled = !isChecked;
                                    opacitySlider.style.opacity = isChecked ? '1' : '0.5';
                                }
                                
                                updateEditorConfigField();
                            };
                            item.appendChild(checkbox);
                            
                            var label = document.createElement('label');
                            label.htmlFor = 'layer_chk_' + name;
                            label.style.cssText = 'flex:1;cursor:pointer;font-size:12px;color:#333;';
                            label.textContent = config.title || name;
                            item.appendChild(label);
                            
                            var opacityContainer = document.createElement('div');
                            opacityContainer.style.cssText = 'display:flex;align-items:center;gap:5px;margin-left:5px;';
                            
                            var opacityLabel = document.createElement('span');
                            opacityLabel.style.cssText = 'font-size:10px;color:#6c757d;min-width:30px;text-align:right;';
                            opacityLabel.textContent = Math.round((config.opacity || 1.0) * 100) + '%';
                            opacityContainer.appendChild(opacityLabel);
                            
                            var opacityInput = document.createElement('input');
                            opacityInput.type = 'range';
                            opacityInput.min = '0';
                            opacityInput.max = '100';
                            opacityInput.value = (config.opacity || 1.0) * 100;
                            opacityInput.style.cssText = 'width:60px;height:4px;cursor:pointer;';
                            opacityInput.id = 'layer_opacity_' + name;
                            opacityInput.disabled = (config.visible === false);
                            opacityInput.style.opacity = (config.visible !== false) ? '1' : '0.5';
                            
                            opacityInput.oninput = function() {
                                var value = parseInt(this.value) / 100;
                                olLayer.setOpacity(value);
                                var label = this.parentNode.querySelector('span');
                                if (label) {
                                    label.textContent = Math.round(value * 100) + '%';
                                }
                            };
                            
                            opacityInput.onchange = function() {
                                updateEditorConfigField();
                            };
                            opacityContainer.appendChild(opacityInput);
                            
                            item.appendChild(opacityContainer);
                            body.appendChild(item);
                        });
                    }
                    
                    function toggleLayerControl() {
                        var bodyEl = document.getElementById('layer_control_body');
                        var icon = document.querySelector('#layer_toggle_btn i');
                        if (!bodyEl || !icon) return;
                        
                        if (bodyEl.style.display === 'none') {
                            bodyEl.style.display = 'block';
                            icon.className = 'fas fa-chevron-up';
                            _isLayerControlCollapsed = false;
                            console.log('📂 Controle de camadas expandido');
                        } else {
                            bodyEl.style.display = 'none';
                            icon.className = 'fas fa-chevron-down';
                            _isLayerControlCollapsed = true;
                            console.log('📁 Controle de camadas recolhido');
                        }
                        updateEditorConfigField();
                    }
                    
                    toggleBtn.onclick = function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        toggleLayerControl();
                    };
                    
                    header.onclick = function(e) {
                        if (e.target === toggleBtn || toggleBtn.contains(e.target)) {
                            return;
                        }
                        toggleLayerControl();
                    };
                    
                    /* Arraste */
                    var isDragging = false;
                    var offsetX, offsetY;
                    
                    header.addEventListener('mousedown', function(e) {
                        if (e.target === toggleBtn || toggleBtn.contains(e.target)) {
                            return;
                        }
                        
                        isDragging = true;
                        var rect = container.getBoundingClientRect();
                        offsetX = e.clientX - rect.left;
                        offsetY = e.clientY - rect.top;
                        container.style.cursor = 'grabbing';
                        container.style.transition = 'none';
                        e.preventDefault();
                    });
                    
                    document.addEventListener('mousemove', function(e) {
                        if (!isDragging) return;
                        
                        var mapContainer = document.getElementById('{$containerId}');
                        var mapRect = mapContainer.getBoundingClientRect();
                        
                        var newX = e.clientX - mapRect.left - offsetX;
                        var newY = e.clientY - mapRect.top - offsetY;
                        
                        newX = Math.max(0, Math.min(mapRect.width - container.offsetWidth, newX));
                        newY = Math.max(0, Math.min(mapRect.height - container.offsetHeight, newY));
                        
                        container.style.left = newX + 'px';
                        container.style.top = newY + 'px';
                        container.style.bottom = 'auto';
                        container.style.right = 'auto';
                    });
                    
                    document.addEventListener('mouseup', function() {
                        if (isDragging) {
                            isDragging = false;
                            container.style.cursor = 'default';
                            container.style.transition = 'all 0.1s ease';
                        }
                    });
                    
                    container.style.left = 'auto';
                    container.style.right = '10px';
                    container.style.bottom = '10px';
                    container.style.top = 'auto';
                    
                    return container;
                }
                
                /* ======================================== */
                /* FUNÇÃO PARA CRIAR DRAW INTERACTION      */
                /* ======================================== */
                function createDrawInteraction(type, freehand) {
                    console.log('🔄 Criando Draw do tipo: ' + type);
                    console.log('  Freehand: ' + (freehand ? 'ATIVADO' : 'DESATIVADO'));
                    
                    if (_draw) {
                        if (typeof _draw.setActive === 'function') {
                            _draw.setActive(false);
                        }
                        if (_draw._pointListener) {
                            _map.un('click', _draw._pointListener);
                        }
                        _draw = null;
                    }
                    
                    if (type === 'Point') {
                        console.log('  ⚠️ Modo ponto: usando clique manual');
                        
                        var pointListener = function(event) {
                            console.log('🖱️ Clique no mapa para ponto');
                            
                            if (!_isDrawingPoint) {
                                console.log('  ⏭️ Modo ponto não está ativo');
                                return;
                            }
                            
                            var hit = _map.forEachFeatureAtPixel(event.pixel, function(feature) {
                                return feature;
                            });
                            
                            if (hit) {
                                console.log('  ⏭️ Clique em geometria existente, ignorando');
                                return;
                            }
                            
                            var coord = event.coordinate;
                            var success = addPointAtCoordinate(coord);
                            
                            if (success) {
                                console.log('✅ Ponto adicionado em:', coord);
                            }
                        };
                        
                        var fakeDraw = {
                            setActive: function(active) {
                                console.log('  Modo ponto ' + (active ? 'ativado' : 'desativado'));
                                _isDrawingPoint = active;
                                if (active) {
                                    _map.on('click', pointListener);
                                    setDrawCursor(true);
                                    console.log('  ✅ Listener de clique para pontos ativado');
                                } else {
                                    _map.un('click', pointListener);
                                    setDrawCursor(false);
                                    console.log('  ✅ Listener de clique para pontos desativado');
                                }
                            },
                            getActive: function() {
                                return _isDrawingPoint;
                            },
                            _pointListener: pointListener
                        };
                        
                        fakeDraw.setActive(true);
                        _draw = fakeDraw;
                        console.log('✅ Draw de ponto criado (clique manual)');
                        return _draw;
                    }
                    
                    var drawOptions = {
                        source: _source,
                        type: type,
                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: '#00ff00',
                                width: 2,
                                lineDash: [4, 4]
                            }),
                            fill: new ol.style.Fill({
                                color: 'rgba(0, 255, 0, 0.1)'
                            }),
                            image: new ol.style.Circle({
                                radius: 6,
                                fill: new ol.style.Fill({ color: '#00ff00' }),
                                stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                            })
                        })
                    };
                    
                    if (freehand) {
                        drawOptions.freehand = true;
                        console.log('  ✅ Modo freehand ativado');
                    } else {
                        drawOptions.freehand = false;
                        drawOptions.condition = ol.events.condition.noModifierKeys;
                        drawOptions.freehandCondition = function() {
                            return false;
                        };
                        console.log('  ✅ Modo ponto-a-ponto ativado');
                    }
                    
                    var draw = new ol.interaction.Draw(drawOptions);
                    
                    draw.on('drawend', function() {
                        console.log('🟢 DRAWEND');
                        saveToHistory();
                        updateGeometryFieldValue();
                        setDrawCursor(false);
                    });
                    
                    draw.on('drawstart', function() {
                        console.log('🟡 DRAWSTART');
                        setDrawCursor(true);
                    });
                    
                    _map.addInteraction(draw);
                    _draw = draw;
                    console.log('✅ Draw criado - Tipo: ' + type);
                    return _draw;
                }
                
                function loadOpenLayers() {
                    console.log('Carregando OpenLayers...');
                    
                    if (typeof ol !== 'undefined') {
                        console.log('✅ OpenLayers ja carregado');
                        createEditor();
                        return;
                    }
                    
                    var script = document.createElement('script');
                    script.src = '{$assetsPath}ol.js';
                    script.onload = function() {
                        console.log('✅ OpenLayers carregado');
                        createEditor();
                    };
                    script.onerror = function() {
                        console.error('❌ Falha ao carregar OpenLayers');
                        setTimeout(loadOpenLayers, 1000);
                    };
                    document.head.appendChild(script);
                }
                
                function createEditor() {
                    console.log('createEditor - Iniciando...');
                    
                    if (typeof ol === 'undefined') {
                        console.error('❌ OpenLayers nao disponivel');
                        return;
                    }
                    
                    var container = document.getElementById('{$containerId}');
                    if (!container) {
                        console.error('❌ Container nao encontrado');
                        setTimeout(createEditor, 500);
                        return;
                    }
                    console.log('✅ Container encontrado');
                    
                    container.innerHTML = '';
                    
                    var center = ol.proj.fromLonLat({$centerJson});
                    var geomData = {$geomJson};
                    var features = [];
                    var zoom = {$zoom};
                    
                    if (geomData) {
                        console.log('Processando geometria...');
                        try {
                            var format = new ol.format.GeoJSON();
                            features = format.readFeatures(geomData, {
                                featureProjection: 'EPSG:3857'
                            });
                            console.log('  Features lidas: ' + features.length);
                            
                            features.forEach(function(feature, idx) {
                                var geom = feature.getGeometry();
                                if (geom) {
                                    console.log('    Feature ' + idx + ' - Tipo: ' + geom.getType());
                                }
                            });
                            
                            if (features.length > 0) {
                                var tempSource = new ol.source.Vector({ features: features });
                                var extent = tempSource.getExtent();
                                if (extent) {
                                    center = ol.extent.getCenter(extent);
                                    zoom = 19;
                                    console.log('  Centro ajustado para a geometria');
                                }
                            }
                        } catch(e) {
                            console.warn('Erro ao processar geometria:', e);
                        }
                    }
                    
                    /* ======================================== */
                    /* CRIAR CAMADAS DO MAPA                  */
                    /* ======================================== */
                    var layerConfigs = {$layersJson};
                    var olLayers = [];
                    
                    console.log('🔄 Criando camadas do mapa...');
                    for (var name in layerConfigs) {
                        var config = layerConfigs[name];
                        var layer = null;
                        
                        if (config.type === 'tile') {
                            if (config.source === 'osm') {
                                layer = new ol.layer.Tile({
                                    source: new ol.source.OSM(),
                                    opacity: config.opacity || 1.0,
                                    visible: (config.visible !== false)
                                });
                            } else {
                                layer = new ol.layer.Tile({
                                    source: new ol.source.OSM(),
                                    opacity: 0.3,
                                    visible: (config.visible !== false)
                                });
                            }
                        } else if (config.type === 'xyz') {
                            layer = new ol.layer.Tile({
                                source: new ol.source.XYZ({
                                    url: config.url,
                                    maxZoom: config.maxZoom || 19
                                }),
                                opacity: config.opacity || 1.0,
                                visible: (config.visible !== false)
                            });
                        } else if (config.type === 'wms') {
                            layer = new ol.layer.Tile({
                                source: new ol.source.TileWMS({
                                    url: config.url,
                                    params: config.params || {},
                                    serverType: config.serverType || 'geoserver'
                                }),
                                opacity: config.opacity || 1.0,
                                visible: (config.visible !== false)
                            });
                        }
                        
                        if (layer) {
                            olLayers.push(layer);
                            console.log('  Camada adicionada: ' + name + ' (visível: ' + (config.visible !== false) + ')');
                        }
                    }
                    
                    if (olLayers.length === 0) {
                        olLayers.push(new ol.layer.Tile({
                            source: new ol.source.OSM()
                        }));
                        console.log('  Camada OSM adicionada (fallback)');
                    }
                    
                    _olLayers = olLayers;
                    
                    /* ======================================== */
                    /* CRIAR MAPA                             */
                    /* ======================================== */
                    var controls;
                    if (ol.control && typeof ol.control.defaults === 'function') {
                        controls = ol.control.defaults({
                            doubleClickZoom: false
                        }).extend([
                            new ol.control.ScaleLine(),
                            new ol.control.FullScreen()
                        ]);
                    } else {
                        controls = [
                            new ol.control.ScaleLine(),
                            new ol.control.FullScreen()
                        ];
                    }
                    
                    var map = new ol.Map({
                        target: '{$containerId}',
                        layers: olLayers,
                        view: new ol.View({
                            center: center,
                            zoom: zoom
                        }),
                        controls: controls
                    });
                    
                    var dblClickInteractions = map.getInteractions().getArray().filter(function(interaction) {
                        return interaction instanceof ol.interaction.DoubleClickZoom;
                    });
                    dblClickInteractions.forEach(function(interaction) {
                        map.removeInteraction(interaction);
                        console.log('  Removido DoubleClickZoom');
                    });
                    
                    console.log('✅ Mapa criado (DoubleClickZoom desabilitado)');
                    _map = map;
                    window._editorMap = map;
                    
                    setTimeout(function() {
                        map.updateSize();
                        console.log('✅ Map.updateSize() executado');
                    }, 100);
                    
                    /* ======================================== */
                    /* CONTROLE DE CAMADAS                    */
                    /* ======================================== */
                    if ({$showLayerControl}) {
                        var layerControl = createLayerControl(olLayers, layerConfigs);
                        container.appendChild(layerControl);
                        console.log('✅ Controle de camadas adicionado');
                    }
                    
                    /* ======================================== */
                    /* CRIAR SOURCE E CAMADA DE EDIÇÃO        */
                    /* ======================================== */
                    var source = new ol.source.Vector({ features: features });
                    console.log('✅ Source criada com ' + features.length + ' features');
                    
                    var layerStyle = createLayerStyle();
                    var layer = new ol.layer.Vector({
                        source: source,
                        name: 'edit_layer',
                        style: layerStyle,
                        updateWhileAnimating: true,
                        updateWhileInteracting: true
                    });
                    
                    map.addLayer(layer);
                    console.log('✅ Camada de edição adicionada ao mapa');
                    _source = source;
                    _layer = layer;
                    window._editorSource = source;
                    window._editorLayer = layer;
                    
                    var selectStyle = new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#ff6600',
                            width: 5
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(255, 102, 0, 0.2)'
                        }),
                        image: new ol.style.Circle({
                            radius: 10,
                            fill: new ol.style.Fill({
                                color: '#ff6600'
                            }),
                            stroke: new ol.style.Stroke({
                                color: '#ffffff',
                                width: 2
                            })
                        })
                    });
                    
                    console.log('Ativando interacoes nativas...');
                    
                    /* ======================================== */
                    /* 1. MODIFY - DESATIVADO POR PADRÃO      */
                    /* ======================================== */
                    _modify = new ol.interaction.Modify({
                        source: source,
                        style: new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: 8,
                                fill: new ol.style.Fill({ color: '#ff0000' }),
                                stroke: new ol.style.Stroke({ color: '#ffffff', width: 2 })
                            })
                        })
                    });
                    _modify.setActive(false);
                    map.addInteraction(_modify);
                    console.log('✅ Modify criado (desativado por padrão)');
                    
                    _modify.on('modifyend', function(e) {
                        console.log('🔴 MODIFYEND - SALVANDO!');
                        saveToHistory();
                        updateGeometryFieldValue();
                    });
                    
                    /* ======================================== */
                    /* 2. SOURCE.CHANGE                       */
                    /* ======================================== */
                    source.on('change', function() {
                        console.log('🟡 SOURCE.CHANGE');
                        if (!_isUndoRedo) {
                            saveToHistory();
                            updateGeometryFieldValue();
                        }
                    });
                    console.log('✅ source.on(change) configurado');
                    
                    /* ======================================== */
                    /* 3. DUPLO CLIQUE                        */
                    /* ======================================== */
                    map.on('dblclick', function(event) {
                        console.log('🖱️ Duplo clique detectado');
                        
                        if (!_modify.getActive()) return;
                        
                        var feature = map.forEachFeatureAtPixel(event.pixel, function(feat) {
                            return feat;
                        });
                        
                        if (!feature) return;
                        
                        var geometry = feature.getGeometry();
                        if (!geometry || geometry.getType() !== 'Polygon') return;
                        
                        var coord = event.coordinate;
                        var polygonCoords = geometry.getCoordinates();
                        var ring = polygonCoords[0];
                        
                        var closest = null;
                        var minDist = Infinity;
                        for (var i = 0; i < ring.length - 1; i++) {
                            var p1 = ring[i];
                            var p2 = ring[i + 1];
                            var dx = p2[0] - p1[0];
                            var dy = p2[1] - p1[1];
                            var t = ((coord[0] - p1[0]) * dx + (coord[1] - p1[1]) * dy) / (dx * dx + dy * dy);
                            t = Math.max(0, Math.min(1, t));
                            var px = p1[0] + t * dx;
                            var py = p1[1] + t * dy;
                            var dist = Math.sqrt(Math.pow(coord[0] - px, 2) + Math.pow(coord[1] - py, 2));
                            if (dist < minDist) {
                                minDist = dist;
                                closest = i;
                            }
                        }
                        
                        if (closest !== null && minDist < 0.0001) {
                            ring.splice(closest + 1, 0, coord);
                            geometry.setCoordinates(polygonCoords);
                            _source.changed();
                            saveToHistory();
                            updateGeometryFieldValue();
                            console.log('✅ Vertice inserido');
                        }
                    });
                    console.log('✅ Inserir vertices com duplo clique');
                    
                    /* ======================================== */
                    /* 4. SELECT - DESATIVADO POR PADRÃO      */
                    /* ======================================== */
                    var newSelect = new ol.interaction.Select({
                        condition: ol.events.condition.click,
                        style: selectStyle,
                        layers: [layer],
                        multi: false,
                        toggleCondition: ol.events.condition.click
                    });
                    newSelect.setActive(false);
                    
                    newSelect.on('select', function(event) {
                        if (event.selected.length > 0) {
                            console.log('✅ Geometria selecionada');
                        } else {
                            console.log('🔓 Seleção removida');
                        }
                    });
                    
                    map.addInteraction(newSelect);
                    _select = newSelect;
                    console.log('✅ Select criado (desativado por padrão)');
                    
                    /* ======================================== */
                    /* 5. TRANSLATE                           */
                    /* ======================================== */
                    var newTranslate = new ol.interaction.Translate({
                        features: _select.getFeatures()
                    });
                    map.addInteraction(newTranslate);
                    _translate = newTranslate;
                    console.log('✅ Translate criado');
                    
                    _translate.on('translateend', function() {
                        console.log('🔵 TRANSLATEEND');
                        saveToHistory();
                        updateGeometryFieldValue();
                    });
                    
                    /* ======================================== */
                    /* 6. SNAP                                */
                    /* ======================================== */
                    _snap = new ol.interaction.Snap({
                        source: source,
                        pixelTolerance: 12
                    });
                    map.addInteraction(_snap);
                    console.log('✅ Snap criado');
                    
                    /* ======================================== */
                    /* 7. DRAW - DESATIVADO POR PADRÃO        */
                    /* ======================================== */
                    _draw = createDrawInteraction('Polygon', _freehandEnabled);
                    if (_draw && typeof _draw.setActive === 'function') {
                        _draw.setActive(false);
                    }
                    console.log('✅ Draw criado (desativado por padrão)');
                    
                    /* ======================================== */
                    /* 8. DELETE                              */
                    /* ======================================== */
                    document.addEventListener('keydown', function(event) {
                        if (event.keyCode === 46 || event.key === 'Delete' || event.key === 'Del') {
                            console.log('🗑️ Delete');
                            var selected = _select.getFeatures();
                            if (selected.getLength() === 0) return;
                            var feature = selected.item(0);
                            if (!feature) return;
                            _source.removeFeature(feature);
                            selected.clear();
                            saveToHistory();
                            updateGeometryFieldValue();
                            console.log('✅ Feature deletada');
                        }
                    });
                    console.log('✅ Delete key');
                    
                    /* ======================================== */
                    /* 9. UNDO/REDO                           */
                    /* ======================================== */
                    document.addEventListener('keydown', function(event) {
                        if ((event.ctrlKey || event.metaKey) && event.key === 'z' && !event.shiftKey) {
                            event.preventDefault();
                            console.log('⌨️ Ctrl+Z');
                            undo();
                        }
                        else if ((event.ctrlKey || event.metaKey) && (event.key === 'y' || (event.key === 'z' && event.shiftKey))) {
                            event.preventDefault();
                            console.log('⌨️ Ctrl+Y');
                            redo();
                        }
                    });
                    console.log('✅ Ctrl+Z / Ctrl+Y');
                    
                    setTimeout(function() {
                        saveToHistory();
                        updateGeometryFieldValue();
                    }, 500);
                    
                    if ({$showToolbar}) {
                        addToolbar(container);
                    }
                    
                    addInstructions(container);
                    
                    console.log('✅ Editor pronto!');
                    console.log('📌 Histórico: ' + _history.length + ' estados');
                    
                }
                
                /* ======================================== */
                /* TOOLBAR COM ÍCONES E TOOLTIPS           */
                /* ======================================== */
                function addToolbar(container) {
                    var toolbar = document.createElement('div');
                    var positionStyle = '{$toolbarPositionStyle}';
                    toolbar.style.cssText = 'position:absolute;' + positionStyle + 'z-index:1000;background:white;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,0.2);padding:5px;display:flex;gap:5px;flex-wrap:wrap;';
                    
                    if (_modify) _modify.setActive(false);
                    if (_select) _select.setActive(false);
                    if (_draw && typeof _draw.setActive === 'function') _draw.setActive(false);
                    
                    var toolbarButtons = {$toolbarButtons};
                    var buttonConfigs = [];
                    
                    if (toolbarButtons.select) {
                        buttonConfigs.push({
                            key: 'select',
                            icon: toolbarButtons.select.icon || 'fa-mouse-pointer',
                            label: toolbarButtons.select.label || 'Selecionar',
                            hint: toolbarButtons.select.hint || 'Selecionar geometria',
                            active: false,
                            action: function() { 
                                if (_select) _select.setActive(true); 
                                if (_modify) _modify.setActive(false); 
                                if (_draw && typeof _draw.setActive === 'function') _draw.setActive(false);
                                setDrawCursor(false);
                                _editorConfig.tools.activeTool = 'select';
                                updateEditorConfigField();
                                console.log('🔍 Selecionar');
                            }
                        });
                    }
                    
                    if (toolbarButtons.draw) {
                        buttonConfigs.push({
                            key: 'draw',
                            icon: toolbarButtons.draw.icon || 'fa-pencil',
                            label: toolbarButtons.draw.label || 'Desenhar',
                            hint: toolbarButtons.draw.hint || 'Desenhar nova geometria',
                            active: false,
                            hasSubmenu: true
                        });
                    }
                    
                    if (toolbarButtons.modify) {
                        buttonConfigs.push({
                            key: 'modify',
                            icon: toolbarButtons.modify.icon || 'fa-edit',
                            label: toolbarButtons.modify.label || 'Modificar',
                            hint: toolbarButtons.modify.hint || 'Modificar geometria existente',
                            active: false,
                            action: function() { 
                                if (_modify) _modify.setActive(true); 
                                if (_draw && typeof _draw.setActive === 'function') _draw.setActive(false); 
                                if (_select) _select.setActive(false);
                                setDrawCursor(false);
                                _editorConfig.tools.activeTool = 'modify';
                                updateEditorConfigField();
                                console.log('🔧 Modificar');
                            }
                        });
                    }
                    
                    if (toolbarButtons.undo) {
                        buttonConfigs.push({
                            key: 'undo',
                            icon: toolbarButtons.undo.icon || 'fa-undo',
                            label: toolbarButtons.undo.label || 'Voltar',
                            hint: toolbarButtons.undo.hint || 'Desfazer última ação (Ctrl+Z)',
                            active: false,
                            isUndo: true,
                            action: function() { 
                                undo();
                            }
                        });
                    }
                    
                    if (toolbarButtons.redo) {
                        buttonConfigs.push({
                            key: 'redo',
                            icon: toolbarButtons.redo.icon || 'fa-redo',
                            label: toolbarButtons.redo.label || 'Refazer',
                            hint: toolbarButtons.redo.hint || 'Refazer ação desfeita (Ctrl+Y)',
                            active: false,
                            isRedo: true,
                            action: function() { 
                                redo();
                            }
                        });
                    }
                    
                    var submenuElement = null;
                    
                    buttonConfigs.forEach(function(btn) {
                        var b = document.createElement('button');
                        b.style.cssText = 'padding:5px 10px;border:1px solid #ccc;border-radius:3px;background:#f8f9fa;cursor:pointer;font-size:12px;display:flex;align-items:center;gap:5px;';
                        
                        var iconSpan = document.createElement('span');
                        iconSpan.className = 'fas ' + btn.icon;
                        iconSpan.style.cssText = 'font-size:14px;';
                        b.appendChild(iconSpan);
                        
                        if (_showToolbarLabels) {
                            var labelSpan = document.createElement('span');
                            labelSpan.textContent = btn.label;
                            b.appendChild(labelSpan);
                        }
                        
                        b.title = btn.hint || btn.label;
                        
                        if (btn.isUndo) {
                            b.id = 'editor_undo_btn';
                            b.disabled = true;
                            b.style.opacity = '0.5';
                        }
                        if (btn.isRedo) {
                            b.id = 'editor_redo_btn';
                            b.disabled = true;
                            b.style.opacity = '0.5';
                        }
                        
                        if (btn.hasSubmenu) {
                            b.style.position = 'relative';
                            
                            var submenu = document.createElement('div');
                            submenu.style.cssText = 'position:absolute;top:100%;left:0;background:white;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.2);padding:4px 0;display:none;z-index:1001;min-width:200px;';
                            submenu.id = 'draw_submenu';
                            
                            var drawTypes = [
                                { label: '🔷 Polígono', type: 'Polygon', freehand: false, hint: 'Desenhar polígono com cliques' },
                                { label: '🔷 Polígono (livre)', type: 'Polygon', freehand: true, hint: 'Desenhar polígono em modo livre' },
                                { label: '🔶 Linha', type: 'LineString', freehand: false, hint: 'Desenhar linha com cliques' },
                                { label: '🔶 Linha (livre)', type: 'LineString', freehand: true, hint: 'Desenhar linha em modo livre' },
                                { label: '⚪ Ponto', type: 'Point', freehand: false, hint: 'Adicionar ponto' },
                                { label: '🌀 Círculo', type: 'Circle', freehand: false, hint: 'Desenhar círculo' }
                            ];
                            
                            drawTypes.forEach(function(item) {
                                var opt = document.createElement('div');
                                opt.innerHTML = item.label;
                                opt.className = 'draw-submenu-item';
                                opt.style.cssText = 'padding:8px 16px;cursor:pointer;border-radius:0;font-size:13px;text-align:left;white-space:nowrap;color:#333;border-bottom:1px solid #f0f0f0;';
                                opt.title = item.hint || item.label;
                                
                                opt.onmouseover = function() { 
                                    this.style.background = '#e9ecef';
                                    this.style.color = '#000';
                                };
                                opt.onmouseout = function() { 
                                    if (!this.classList.contains('active-type')) {
                                        this.style.background = 'transparent';
                                        this.style.color = '#333';
                                    }
                                };
                                opt.onclick = function(e) {
                                    e.stopPropagation();
                                    console.log('🎯 Tipo: ' + item.type + ' | Freehand: ' + item.freehand);
                                    
                                    submenu.querySelectorAll('.draw-submenu-item').forEach(function(el) {
                                        el.classList.remove('active-type');
                                        el.style.background = 'transparent';
                                        el.style.color = '#333';
                                    });
                                    this.classList.add('active-type');
                                    this.style.background = '#007bff';
                                    this.style.color = 'white';
                                    
                                    if (_modify) _modify.setActive(false);
                                    if (_select) _select.setActive(false);
                                    if (_draw && typeof _draw.setActive === 'function') _draw.setActive(false);
                                    
                                    _draw = createDrawInteraction(item.type, item.freehand);
                                    if (_draw && typeof _draw.setActive === 'function') {
                                        _draw.setActive(true);
                                    }
                                    
                                    _editorConfig.tools.activeTool = 'draw';
                                    _editorConfig.tools.drawType = item.type;
                                    _editorConfig.tools.freehand = item.freehand;
                                    updateEditorConfigField();
                                    
                                    submenu.style.display = 'none';
                                    _drawSubmenuOpen = false;
                                    
                                    toolbar.querySelectorAll('button').forEach(function(btn) {
                                        if (!btn.id || (btn.id !== 'editor_undo_btn' && btn.id !== 'editor_redo_btn')) {
                                            btn.style.background = '#f8f9fa';
                                            btn.style.color = '#000';
                                            btn.style.borderColor = '#ccc';
                                            btn.classList.remove('active');
                                        }
                                    });
                                    b.style.background = '#007bff';
                                    b.style.color = 'white';
                                    b.style.borderColor = '#007bff';
                                    b.classList.add('active');
                                    
                                    console.log('✅ Desenhar - Tipo: ' + item.type + ' | Freehand: ' + item.freehand);
                                };
                                submenu.appendChild(opt);
                            });
                            
                            b.appendChild(submenu);
                            submenuElement = submenu;
                            
                            b.onclick = function(e) {
                                e.stopPropagation();
                                console.log('📋 Alternando submenu');
                                                
                                if (submenu.style.display === 'block') {
                                    submenu.style.display = 'none';
                                    _drawSubmenuOpen = false;
                                } else {
                                    document.querySelectorAll('#draw_submenu').forEach(function(el) {
                                        el.style.display = 'none';
                                    });
                                    submenu.style.display = 'block';
                                    _drawSubmenuOpen = true;
                                }
                            };
                        } else {
                            b.onclick = function(e) {
                                if (this.disabled) return;
                                
                                toolbar.querySelectorAll('button').forEach(function(btn) {
                                    if (!btn.id || (btn.id !== 'editor_undo_btn' && btn.id !== 'editor_redo_btn')) {
                                        btn.style.background = '#f8f9fa';
                                        btn.style.color = '#000';
                                        btn.style.borderColor = '#ccc';
                                        btn.classList.remove('active');
                                    }
                                });
                                this.style.background = '#007bff';
                                this.style.color = 'white';
                                this.style.borderColor = '#007bff';
                                this.classList.add('active');
                                
                                if (submenuElement) {
                                    submenuElement.style.display = 'none';
                                    _drawSubmenuOpen = false;
                                }
                                
                                if (btn.action) btn.action();
                            };
                        }
                        
                        b.onmouseover = function() { 
                            if (!this.disabled && !this.classList.contains('active')) {
                                this.style.background = '#e9ecef';
                            }
                        };
                        b.onmouseout = function() { 
                            if (!this.classList.contains('active') && !this.disabled) {
                                this.style.background = '#f8f9fa';
                            }
                        };
                        
                        if (btn.active) {
                            b.style.background = '#007bff';
                            b.style.color = 'white';
                            b.style.borderColor = '#007bff';
                            b.classList.add('active');
                        }
                        toolbar.appendChild(b);
                    });
                    
                    document.addEventListener('click', function(e) {
                        if (submenuElement && !submenuElement.parentNode.contains(e.target)) {
                            submenuElement.style.display = 'none';
                            _drawSubmenuOpen = false;
                        }
                    });
                    
                    container.appendChild(toolbar);
                    console.log('✅ Toolbar adicionada (labels: ' + (_showToolbarLabels ? 'SIM' : 'NÃO') + ')');
                    
                    updateUndoRedoButtons();
                }
                
                /* ======================================== */
                /* INSTRUCOES                              */
                /* ======================================== */
                function addInstructions(container) {
                    var div = document.createElement('div');
                    div.style.cssText = 'position:absolute;bottom:10px;left:50%;transform:translateX(-50%);z-index:1000;background:rgba(0,0,0,0.7);color:white;padding:10px 20px;border-radius:5px;font-size:12px;text-align:center;max-width:90%;';
                    var freehandText = _freehandEnabled ? ' (modo livre)' : '';
                    div.innerHTML = '🖱️ Clique → desenhar' + freehandText + ' | 🔄 Arraste → mover | ❌ Clique no vertice → deletar | ✏️ Duplo clique → inserir vertice | ↩️ Ctrl+Z → desfazer | ↪️ Ctrl+Y → refazer';
                    container.appendChild(div);
                    
                    setTimeout(function() {
                        if (div.parentNode) {
                            div.style.opacity = '0';
                            div.style.transition = 'opacity 1s';
                            setTimeout(function() {
                                if (div.parentNode) div.parentNode.removeChild(div);
                            }, 1000);
                        }
                    }, 12000);
                }
                
                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                    loadOpenLayers();
                } else {
                    document.addEventListener('DOMContentLoaded', loadOpenLayers);
                }
                
            })();
        ");
    }
}
