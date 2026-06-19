<?php

namespace MarceloNees\Plugins\OpenLayersEditor;

use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Base\TStyle;

/**
 * OpenLayersEditor - Componente para edição de geometrias
 * 
 * @author Marcelo Barreto Nees <marcelo.linux@gmail.com>
 * @version 1.0
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
    private $useOLE = true;
    private $layers = [];
    private $options = [];

    /**
     * Construtor
     * @param array $options Opções do editor
     */
    public function __construct($options = [])
    {
        parent::__construct('div');

        $this->id = 'ol_editor_' . uniqid();
        $this->options = array_merge([
            'useOLE' => true,
            'showToolbar' => true,
            'editLayers' => ['Polygon', 'LineString', 'Point'],
            'controls' => ['Draw', 'Modify', 'CAD', 'Delete', 'Rotate']
        ], $options);

        $this->useOLE = $this->options['useOLE'];
    }

    /**
     * Define o tamanho do mapa
     */
    public function setSize($width, $height)
    {
        $this->width = is_numeric($width) ? "{$width}px" : $width;
        $this->height = is_numeric($height) ? "{$height}px" : $height;
        return $this;
    }

    /**
     * Define a geometria inicial
     */
    public function setGeometry($geom)
    {
        $this->geom = $geom;
        return $this;
    }

    /**
     * Define o centro do mapa
     */
    public function setCenter($lat, $lng)
    {
        $this->center = [$lng, $lat];
        return $this;
    }

    /**
     * Define o zoom
     */
    public function setZoom($zoom)
    {
        $this->zoom = $zoom;
        return $this;
    }

    /**
     * Adiciona uma camada ao mapa
     */
    public function addLayer($name, $config)
    {
        $this->layers[$name] = $config;
        return $this;
    }

    /**
     * Renderiza o componente
     */
    public function show()
    {
        /* Cria o container do mapa */
        $style = new TStyle("#{$this->id}");
        $style->width = $this->width;
        $style->height = $this->height;
        $style->border = '1px solid #ccc';
        $style->background = '#f0f0f0';
        $style->show();

        $content = new TElement('div');
        $content->id = $this->id;
        $content->class = 'openlayers-editor';
        $content->add('Carregando editor...');

        parent::add($content);

        /* Carrega os assets e inicializa o mapa */
        $this->loadAssets();
        $this->initMap();

        parent::show();
    }

    /**
     * Carrega os assets necessários
     */
    private function loadAssets()
    {
        $basePath = 'vendor/marcelonees/plugins/src/OpenLayersEditor/';

        /* CSS */
        TStyle::importFromFile($basePath . 'ol.css');
        TStyle::importFromFile($basePath . 'ol-popup.css');

        if ($this->useOLE) {
            TStyle::importFromFile($basePath . 'openlayers-editor.css');
        }
    }

    /**
     * Inicializa o mapa
     */
    private function initMap()
    {
        $id = $this->id;
        $center = $this->center;
        $zoom = $this->zoom;
        $geom = $this->geom ? json_encode($this->geom) : 'null';
        $useOLE = $this->useOLE ? 'true' : 'false';
        $layers = json_encode($this->layers);

        TScript::create("
            (function() {
                console.log('=== OpenLayersEditor - INICIANDO ===');
                console.log('ID:', '{$id}');
                console.log('Use OLE:', {$useOLE});
                
                /* Carrega os scripts */
                function loadScripts() {
                    var basePath = 'vendor/marcelonees/plugins/src/OpenLayersEditor/';
                    var scripts = [];
                    
                    /* OpenLayers */
                    if (typeof ol === 'undefined') {
                        scripts.push(basePath + 'ol.js');
                    }
                    
                    /* Turf.js */
                    if (typeof turf === 'undefined') {
                        scripts.push(basePath + 'turf.min.js');
                    }
                    
                    /* Popup */
                    if (typeof Popup === 'undefined') {
                        scripts.push(basePath + 'ol-popup.js');
                    }
                    
                    /* OLE */
                    if ({$useOLE} && typeof ole === 'undefined') {
                        scripts.push(basePath + 'openlayers-editor.js');
                    }
                    
                    if (scripts.length === 0) {
                        createEditor();
                        return;
                    }
                    
                    loadScriptsSequentially(scripts, 0);
                }
                
                function loadScriptsSequentially(scripts, index) {
                    if (index >= scripts.length) {
                        createEditor();
                        return;
                    }
                    
                    console.log('Carregando:', scripts[index]);
                    var script = document.createElement('script');
                    script.src = scripts[index];
                    script.onload = function() {
                        console.log('✅ Carregado:', scripts[index]);
                        loadScriptsSequentially(scripts, index + 1);
                    };
                    script.onerror = function() {
                        console.error('❌ Falha ao carregar:', scripts[index]);
                        loadScriptsSequentially(scripts, index + 1);
                    };
                    document.head.appendChild(script);
                }
                
                /* ======================================== */
                /* CRIA O EDITOR                          */
                /* ======================================== */
                function createEditor() {
                    console.log('createEditor - Iniciando...');
                    
                    var container = document.getElementById('{$id}');
                    if (!container) {
                        console.error('❌ Container não encontrado');
                        setTimeout(createEditor, 500);
                        return;
                    }
                    
                    /* Limpa o container */
                    container.innerHTML = '';
                    
                    /* Cria o target do mapa */
                    var mapId = 'ol_map_' + Date.now();
                    var mapDiv = document.createElement('div');
                    mapDiv.id = mapId;
                    mapDiv.style.cssText = 'width: 100%; height: 100%;';
                    container.appendChild(mapDiv);
                    
                    console.log('✅ Map target criado:', mapId);
                    
                    /* ======================================== */
                    /* CRIA O MAPA                            */
                    /* ======================================== */
                    var center = ol.proj.fromLonLat({$center});
                    var geomData = {$geom};
                    var features = [];
                    
                    if (geomData) {
                        try {
                            var format = new ol.format.GeoJSON();
                            features = format.readFeatures(geomData, {
                                featureProjection: 'EPSG:3857'
                            });
                            console.log('Features lidas:', features.length);
                            
                            if (features.length > 0) {
                                var tempSource = new ol.source.Vector({ features: features });
                                var extent = tempSource.getExtent();
                                if (extent) {
                                    var center3857 = ol.extent.getCenter(extent);
                                    center = center3857;
                                    var zoom = 19;
                                }
                            }
                        } catch(e) {
                            console.warn('Erro ao processar geometria:', e);
                        }
                    }
                    
                    /* Camadas */
                    var layers = [];
                    
                    /* Camada OSM */
                    layers.push(new ol.layer.Tile({
                        source: new ol.source.OSM(),
                        opacity: 0.3
                    }));
                    
                    /* Ortofoto */
                    layers.push(new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: 'https://www.jaraguadosul.sc.gov.br/geo/ortomosaico2020/{z}/{x}/{y}.png',
                            maxZoom: 19
                        }),
                        opacity: 1.0
                    }));
                    
                    /* Cria o mapa */
                    var map = new ol.Map({
                        target: mapId,
                        layers: layers,
                        view: new ol.View({
                            center: center,
                            zoom: zoom || 15
                        }),
                        controls: ol.control.defaults().extend([
                            new ol.control.ScaleLine(),
                            new ol.control.FullScreen()
                        ])
                    });
                    
                    console.log('✅ Mapa criado');
                    window._editorMap = map;
                    
                    /* ======================================== */
                    /* CARREGA A GEOMETRIA                     */
                    /* ======================================== */
                    if (features.length > 0) {
                        var source = new ol.source.Vector({ features: features });
                        var layer = new ol.layer.Vector({
                            source: source,
                            name: 'edit_layer',
                            style: new ol.style.Style({
                                stroke: new ol.style.Stroke({
                                    color: '#00ff00',
                                    width: 3
                                }),
                                fill: new ol.style.Fill({
                                    color: 'rgba(0, 255, 0, 0.1)'
                                })
                            })
                        });
                        map.addLayer(layer);
                        console.log('✅ Geometria carregada');
                        
                        /* ======================================== */
                        /* INICIALIZA O EDITOR                    */
                        /* ======================================== */
                        if ({$useOLE} && typeof ole !== 'undefined') {
                            initOLE(map, source, container);
                        } else {
                            initNative(map, source, container);
                        }
                        
                    } else {
                        container.innerHTML = '<div style=\"position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.3);z-index:1000;text-align:center;\"><b>ℹ️ Nenhuma geometria carregada</b></div>';
                    }
                }
                
                /* ======================================== */
                /* INICIALIZA OLE                          */
                /* ======================================== */
                function initOLE(map, source, container) {
                    console.log('Inicializando OLE...');
                    try {
                        var editor = new ole.Editor(map);
                        console.log('✅ Editor OLE criado');
                        
                        var controls = [];
                        
                        /* Draw */
                        var draw = new ole.control.Draw({
                            source: source,
                            type: 'Polygon'
                        });
                        controls.push(draw);
                        console.log('✅ Draw control criado');
                        
                        /* Modify */
                        var modify = new ole.control.Modify({
                            source: source
                        });
                        controls.push(modify);
                        console.log('✅ Modify control criado');
                        
                        /* CAD */
                        var cad = new ole.control.CAD({
                            source: source
                        });
                        controls.push(cad);
                        console.log('✅ CAD control criado');
                        
                        editor.addControls(controls);
                        console.log('✅ Controles adicionados');
                        
                        window._oleEditor = editor;
                        
                        /* Evento de atualização */
                        source.on('change', function() {
                            updateGeometryField(source);
                        });
                        
                        setTimeout(function() {
                            updateGeometryField(source);
                        }, 500);
                        
                        console.log('✅ OLE inicializado com sucesso!');
                        
                    } catch(e) {
                        console.error('❌ Erro OLE:', e);
                        initNative(map, source, container);
                    }
                }
                
                /* ======================================== */
                /* INICIALIZA NATIVO (FALLBACK)            */
                /* ======================================== */
                function initNative(map, source, container) {
                    console.log('Inicializando fallback nativo...');
                    
                    /* Select */
                    var select = new ol.interaction.Select({
                        condition: ol.events.condition.click,
                        style: new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: 10,
                                fill: new ol.style.Fill({ color: '#ff6600' }),
                                stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 })
                            })
                        })
                    });
                    map.addInteraction(select);
                    
                    /* Translate */
                    var translate = new ol.interaction.Translate({
                        features: select.getFeatures()
                    });
                    map.addInteraction(translate);
                    
                    /* Modify */
                    var modify = new ol.interaction.Modify({
                        source: source,
                        deleteCondition: function(e) {
                            return ol.events.condition.shiftKeyOnly(e) && ol.events.condition.click(e);
                        },
                        insertVertexCondition: function(e) {
                            return ol.events.condition.doubleClick(e);
                        }
                    });
                    map.addInteraction(modify);
                    
                    /* Draw */
                    var draw = new ol.interaction.Draw({
                        source: source,
                        type: 'Polygon',
                        condition: function(e) {
                            return ol.events.condition.altKeyOnly(e) && ol.events.condition.click(e);
                        }
                    });
                    map.addInteraction(draw);
                    
                    /* Atualiza campo geom */
                    source.on('change', function() {
                        updateGeometryField(source);
                    });
                    
                    console.log('✅ Fallback nativo ativado');
                }
                
                /* ======================================== */
                /* ATUALIZA CAMPO GEOM                      */
                /* ======================================== */
                function updateGeometryField(source) {
                    var features = source.getFeatures();
                    if (features.length > 0) {
                        try {
                            var format = new ol.format.GeoJSON();
                            var geomJson = format.writeFeatures(features, {
                                dataProjection: 'EPSG:4326',
                                featureProjection: 'EPSG:3857'
                            });
                            
                            /* Dispara evento personalizado */
                            var event = new CustomEvent('geometryChanged', {
                                detail: { geometry: geomJson }
                            });
                            document.dispatchEvent(event);
                            
                            console.log('✅ Geometria atualizada');
                        } catch(e) {
                            console.error('❌ Erro:', e);
                        }
                    }
                }
                
                /* ======================================== */
                /* INICIA O CARREGAMENTO                   */
                /* ======================================== */
                console.log('Iniciando carregamento...');
                
                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                    loadScripts();
                } else {
                    document.addEventListener('DOMContentLoaded', loadScripts);
                }
                
            })();
        ");
    }
}
