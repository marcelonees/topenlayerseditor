/**
 * OpenLayers Editor - Gerenciador de edição de geometrias
 * @version 1.0
 * @author Marcelo Barreto Nees <marcelo.linux@gmail.com>
 * @license MIT
 */

(function () {
    "use strict";

    /* ======================================== */
    /* VERIFICAÇÃO DE DEPENDÊNCIAS              */
    /* ======================================== */
    if (typeof ol === "undefined") {
        console.error("OpenLayersEditor: OpenLayers não está disponível");
        return;
    }

    /* ======================================== */
    /* MÓDULO PRINCIPAL - GeoMapEditorApp      */
    /* ======================================== */
    var GeoMapEditorApp = (function () {
        /* Verifica se OpenLayers está carregado */
        if (typeof ol === "undefined") {
            console.error("OpenLayers não está carregado!");
            return null;
        }

        /* Variáveis privadas */
        let _map = null;
        let _source = null;
        let _layer = null;
        let _modify = null;
        let _select = null;
        let _draw = null;
        let _translate = null;
        let _snap = null;
        let _popup = null;
        let _history = [];
        let _historyIndex = -1;
        let _maxHistory = 100;
        let _isUndoRedo = false;
        let _isDrawingPoint = false;
        let _originalCursor = null;
        let _drawSubmenuOpen = false;
        let _isLayerControlCollapsed = false;
        let _initialized = false;
        let _pendingGeom = null;
        let _olLayers = [];
        let _editorConfig = {
            layers: {},
            tools: {
                activeTool: null,
                drawType: null,
                freehand: false,
            },
            ui: {
                showToolbarLabels: true,
                toolbarPosition: "top-right",
                layerControlCollapsed: false,
            },
            map: {
                center: null,
                zoom: null,
            },
        };

        /* Configurações da instância */
        let _config = {
            containerId: null,
            geometryFieldId: null,
            editorConfigFieldId: null,
            layers: {},
            freehand: false,
            showToolbar: true,
            showToolbarLabels: true,
            toolbarPosition: "top-right",
            showLayerControl: true,
            toolbarButtons: {},
            center: [-49.0904928, -26.504104],
            zoom: 15,
            restoreConfig: null,
            geom: null,
        };

        /* ======================================== */
        /* INICIALIZAÇÃO                           */
        /* ======================================== */
        function _initEditor(config) {
            if (_initialized) {
                console.log("⚠️ Editor já inicializado");
                return;
            }

            console.log("🚀 GeoMapEditorApp.init()", config);

            /* Mescla as configurações */
            _config = Object.assign(_config, config);

            /* Inicializa o editor */
            _createEditor();
            _initialized = true;

            /* Restaurar configurações se houver */
            if (_config.restoreConfig) {
                setTimeout(function () {
                    restoreEditorConfig(_config.restoreConfig);
                }, 1500);
            }
        }

        function _createEditor() {
            const container = document.getElementById(_config.containerId);
            if (!container) {
                console.error("❌ Container não encontrado");
                return;
            }

            console.log("📝 Criando editor...");

            /* Limpa o container */
            container.innerHTML = "";

            /* Preparar dados */
            const center = ol.proj.fromLonLat(_config.center);
            const features = _prepareFeatures(_config.geom || null);
            const zoom = _config.zoom;

            /* ======================================== */
            /* CRIAR CAMADAS DO MAPA                   */
            /* ======================================== */
            _olLayers = [];
            const layerConfigs = _config.layers || {};

            console.log("🔄 Criando camadas do mapa...");
            for (const name in layerConfigs) {
                const config = layerConfigs[name];
                let layer = null;

                if (config.type === "tile") {
                    if (config.source === "osm") {
                        layer = new ol.layer.Tile({
                            source: new ol.source.OSM(),
                            opacity: config.opacity || 1.0,
                            visible: config.visible !== false,
                        });
                    } else {
                        layer = new ol.layer.Tile({
                            source: new ol.source.OSM(),
                            opacity: 0.3,
                            visible: config.visible !== false,
                        });
                    }
                } else if (config.type === "xyz") {
                    layer = new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: config.url,
                            maxZoom: config.maxZoom || 19,
                        }),
                        opacity: config.opacity || 1.0,
                        visible: config.visible !== false,
                    });
                } else if (config.type === "wms") {
                    layer = new ol.layer.Tile({
                        source: new ol.source.TileWMS({
                            url: config.url,
                            params: config.params || {},
                            serverType: config.serverType || "geoserver",
                        }),
                        opacity: config.opacity || 1.0,
                        visible: config.visible !== false,
                    });
                }

                if (layer) {
                    _olLayers.push(layer);
                    console.log(
                        `  Camada adicionada: ${name} (visível: ${config.visible !== false})`,
                    );
                }
            }

            if (_olLayers.length === 0) {
                _olLayers.push(
                    new ol.layer.Tile({
                        source: new ol.source.OSM(),
                    }),
                );
                console.log("  Camada OSM adicionada (fallback)");
            }

            /* ======================================== */
            /* CRIAR MAPA                              */
            /* ======================================== */
            let controls;
            if (ol.control && typeof ol.control.defaults === "function") {
                controls = ol.control
                    .defaults({
                        attribution: false,
                        doubleClickZoom: false,
                    })
                    .extend([
                        new ol.control.ScaleLine(),
                        new ol.control.FullScreen(),
                    ]);
            } else {
                controls = [
                    new ol.control.ScaleLine(),
                    new ol.control.FullScreen(),
                ];
            }

            _map = new ol.Map({
                target: _config.containerId,
                layers: _olLayers,
                view: new ol.View({
                    center: center,
                    zoom: zoom,
                }),
                controls: controls,
            });

            /* Remover DoubleClickZoom */
            try {
                const interactions = _map.getInteractions().getArray();
                const dblClickInteractions = interactions.filter(
                    (interaction) =>
                        interaction instanceof ol.interaction.DoubleClickZoom,
                );
                dblClickInteractions.forEach((interaction) => {
                    _map.removeInteraction(interaction);
                });
                console.log("✅ DoubleClickZoom removido");
            } catch (e) {
                console.warn("⚠️ Não foi possível remover DoubleClickZoom:", e);
            }

            console.log("✅ Mapa criado");

            /* ======================================== */
            /* CONTROLE DE CAMADAS                     */
            /* ======================================== */
            if (_config.showLayerControl) {
                const layerControl = _createLayerControl(
                    _olLayers,
                    layerConfigs,
                );
                container.appendChild(layerControl);
                console.log("✅ Controle de camadas adicionado");
            }

            /* ======================================== */
            /* CRIAR SOURCE E CAMADA DE EDIÇÃO         */
            /* ======================================== */
            _source = new ol.source.Vector({ features: features });
            console.log(`✅ Source criada com ${features.length} features`);

            const layerStyle = _createLayerStyle();
            _layer = new ol.layer.Vector({
                source: _source,
                name: "edit_layer",
                style: layerStyle,
                updateWhileAnimating: true,
                updateWhileInteracting: true,
            });

            _map.addLayer(_layer);
            console.log("✅ Camada de edição adicionada ao mapa");

            /* ======================================== */
            /* INTERAÇÕES                              */
            /* ======================================== */
            _setupInteractions();

            /* ======================================== */
            /* HISTÓRICO                              */
            /* ======================================== */
            _setupHistory();

            /* ======================================== */
            /* TOOLBAR                                */
            /* ======================================== */
            if (_config.showToolbar) {
                _addToolbar(container);
            }

            /* ======================================== */
            /* INSTRUÇÕES                             */
            /* ======================================== */
            _addInstructions(container);

            /* ======================================== */
            /* AJUSTAR VISÃO PARA A GEOMETRIA INICIAL  */
            /* ======================================== */
            if (features && features.length > 0) {
                setTimeout(function () {
                    _map.updateSize();
                    console.log("✅ Map.updateSize() executado");
                    _fitToGeometry(features);
                }, 500);
            } else {
                setTimeout(function () {
                    _map.updateSize();
                    console.log("✅ Map.updateSize() executado");
                }, 100);
            }

            console.log("✅ Editor pronto!");
            console.log(`📌 Histórico: ${_history.length} estados`);

            /* Salvar estado inicial */
            setTimeout(function () {
                _saveToHistory();
                _updateGeometryField();
            }, 500);
        }

        function _prepareFeatures(geomData) {
            const features = [];

            if (geomData) {
                console.log("Processando geometria...");
                try {
                    const format = new ol.format.GeoJSON();
                    const parsed = format.readFeatures(geomData, {
                        featureProjection: "EPSG:3857",
                    });
                    features.push(...parsed);
                    console.log(`  Features lidas: ${features.length}`);

                    features.forEach((feature, idx) => {
                        const geom = feature.getGeometry();
                        if (geom) {
                            console.log(
                                `    Feature ${idx} - Tipo: ${geom.getType()}`,
                            );
                        }
                    });
                } catch (e) {
                    console.warn("Erro ao processar geometria:", e);
                }
            }

            return features;
        }

        function _fitToGeometry(features, padding) {
            padding = padding || 50;

            if (!_map || !features || features.length === 0) {
                console.warn(
                    "⚠️ Não é possível ajustar a visão: sem mapa ou features",
                );
                return;
            }

            try {
                console.log("🔍 Ajustando visão para a geometria...");

                const tempSource = new ol.source.Vector({ features: features });
                const extent = tempSource.getExtent();

                if (!extent || !isFinite(extent[0])) {
                    console.warn("⚠️ Extent inválido:", extent);
                    return;
                }

                console.log("📐 Extent:", extent);

                _map.getView().fit(extent, {
                    padding: [padding, padding, padding, padding],
                    maxZoom: 19,
                    duration: 1000,
                });

                console.log("✅ Visão ajustada para a geometria");
            } catch (e) {
                console.error("❌ Erro ao ajustar visão:", e);
            }
        }

        function _setupInteractions() {
            const map = _map;
            const source = _source;

            const selectStyle = new ol.style.Style({
                stroke: new ol.style.Stroke({
                    color: "#ff6600",
                    width: 5,
                }),
                fill: new ol.style.Fill({
                    color: "rgba(255, 102, 0, 0.2)",
                }),
                image: new ol.style.Circle({
                    radius: 10,
                    fill: new ol.style.Fill({
                        color: "#ff6600",
                    }),
                    stroke: new ol.style.Stroke({
                        color: "#ffffff",
                        width: 2,
                    }),
                }),
            });

            /* 1. MODIFY */
            _modify = new ol.interaction.Modify({
                source: source,
                style: new ol.style.Style({
                    image: new ol.style.Circle({
                        radius: 8,
                        fill: new ol.style.Fill({ color: "#ff0000" }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 2,
                        }),
                    }),
                }),
            });
            _modify.setActive(false);
            map.addInteraction(_modify);

            _modify.on("modifyend", function (e) {
                console.log("🔴 MODIFYEND - SALVANDO!");
                _saveToHistory();
                _updateGeometryField();
            });
            console.log("✅ Modify criado (desativado por padrão)");

            /* 2. SOURCE CHANGE */
            source.on("change", function () {
                if (!_isUndoRedo) {
                    _saveToHistory();
                    _updateGeometryField();
                }
            });
            console.log("✅ source.on(change) configurado");

            /* 3. DUPLO CLIQUE - Inserir vértice */
            map.on("dblclick", function (event) {
                if (!_modify.getActive()) return;

                const feature = map.forEachFeatureAtPixel(
                    event.pixel,
                    function (feat) {
                        return feat;
                    },
                );
                if (!feature) return;

                const geometry = feature.getGeometry();
                if (!geometry || geometry.getType() !== "Polygon") return;

                const coord = event.coordinate;
                const polygonCoords = geometry.getCoordinates();
                const ring = polygonCoords[0];

                let closest = null;
                let minDist = Infinity;
                for (let i = 0; i < ring.length - 1; i++) {
                    const p1 = ring[i];
                    const p2 = ring[i + 1];
                    const dx = p2[0] - p1[0];
                    const dy = p2[1] - p1[1];
                    const t =
                        ((coord[0] - p1[0]) * dx + (coord[1] - p1[1]) * dy) /
                        (dx * dx + dy * dy);
                    const tClamped = Math.max(0, Math.min(1, t));
                    const px = p1[0] + tClamped * dx;
                    const py = p1[1] + tClamped * dy;
                    const dist = Math.sqrt(
                        Math.pow(coord[0] - px, 2) + Math.pow(coord[1] - py, 2),
                    );
                    if (dist < minDist) {
                        minDist = dist;
                        closest = i;
                    }
                }

                if (closest !== null && minDist < 0.0001) {
                    ring.splice(closest + 1, 0, coord);
                    geometry.setCoordinates(polygonCoords);
                    source.changed();
                    _saveToHistory();
                    _updateGeometryField();
                    console.log("✅ Vértice inserido");
                }
            });
            console.log("✅ Inserir vértices com duplo clique");

            /* 4. SELECT */
            _select = new ol.interaction.Select({
                condition: ol.events.condition.click,
                style: selectStyle,
                layers: [_layer],
                multi: false,
                toggleCondition: ol.events.condition.click,
            });
            _select.setActive(false);
            _select.on("select", function (event) {
                if (event.selected.length > 0) {
                    console.log("✅ Geometria selecionada");
                } else {
                    console.log("🔓 Seleção removida");
                }
            });
            map.addInteraction(_select);
            console.log("✅ Select criado (desativado por padrão)");

            /* 5. TRANSLATE */
            _translate = new ol.interaction.Translate({
                features: _select.getFeatures(),
            });
            map.addInteraction(_translate);
            _translate.on("translateend", function () {
                console.log("🔵 TRANSLATEEND");
                _saveToHistory();
                _updateGeometryField();
            });
            console.log("✅ Translate criado");

            /* 6. SNAP */
            _snap = new ol.interaction.Snap({
                source: source,
                pixelTolerance: 12,
            });
            map.addInteraction(_snap);
            console.log("✅ Snap criado");

            /* 7. DRAW - Desativado por padrão */
            _draw = _createDrawInteraction(
                "Polygon",
                _config.freehand || false,
            );
            if (_draw && typeof _draw.setActive === "function") {
                _draw.setActive(false);
            }
            console.log("✅ Draw criado (desativado por padrão)");

            /* 8. DELETE Key */
            document.addEventListener("keydown", function (event) {
                if (
                    event.keyCode === 46 ||
                    event.key === "Delete" ||
                    event.key === "Del"
                ) {
                    const selected = _select.getFeatures();
                    if (selected.getLength() === 0) return;
                    const feature = selected.item(0);
                    if (!feature) return;
                    source.removeFeature(feature);
                    selected.clear();
                    _saveToHistory();
                    _updateGeometryField();
                    console.log("✅ Feature deletada");
                }
            });
            console.log("✅ Delete key configurado");

            /* 9. Teclas de atalho (desfazer/refazer) */
            document.addEventListener("keydown", function (event) {
                if (
                    (event.ctrlKey || event.metaKey) &&
                    event.key === "z" &&
                    !event.shiftKey
                ) {
                    event.preventDefault();
                    undo();
                } else if (
                    (event.ctrlKey || event.metaKey) &&
                    (event.key === "y" || (event.key === "z" && event.shiftKey))
                ) {
                    event.preventDefault();
                    redo();
                }
            });
            console.log("✅ Ctrl+Z / Ctrl+Y configurados");
        }

        function _setupHistory() {
            _history = [];
            _historyIndex = -1;
        }

        function _saveToHistory() {
            if (!_source) return;
            if (_isUndoRedo) return;

            const features = _source.getFeatures();
            const data = [];

            features.forEach(function (feature) {
                const geom = feature.getGeometry();
                if (geom) {
                    try {
                        const convertedGeom = _convertGeometryForSave(geom);
                        if (convertedGeom) {
                            const geomJson =
                                new ol.format.GeoJSON().writeGeometry(
                                    convertedGeom,
                                );
                            data.push({
                                geometry: geomJson,
                                properties: feature.getProperties(),
                            });
                        }
                    } catch (e) {
                        console.warn("Erro ao salvar feature:", e);
                    }
                }
            });

            if (data.length === 0) {
                console.log("⚠️ Nenhum dado para salvar");
                return;
            }

            if (_historyIndex < _history.length - 1) {
                _history = _history.slice(0, _historyIndex + 1);
            }

            _history.push({
                features: data,
                timestamp: Date.now(),
            });

            if (_history.length > _maxHistory) {
                _history.shift();
            }

            _historyIndex = _history.length - 1;
            console.log(`📝 Histórico salvo (${_history.length} estados)`);
            _updateUndoRedoButtons();
        }

        function _restoreFromHistory(index) {
            if (index < 0 || index >= _history.length) return;
            if (!_source || !_map) return;

            console.log(
                `📌 Restaurando estado ${index + 1}/${_history.length}`,
            );

            _isUndoRedo = true;
            const state = _history[index];

            _source.clear();

            state.features.forEach(function (item) {
                try {
                    const geometry = new ol.format.GeoJSON().readGeometry(
                        item.geometry,
                    );
                    const feature = new ol.Feature({
                        geometry: geometry,
                    });
                    feature.setProperties(item.properties || {});
                    _source.addFeature(feature);
                } catch (e) {
                    console.warn("Erro ao restaurar feature:", e);
                }
            });

            _historyIndex = index;
            _source.changed();
            if (_layer) {
                _layer.setSource(null);
                _layer.setSource(_source);
                _layer.changed();
            }

            const currentFeatures = _source.getFeatures();
            if (currentFeatures && currentFeatures.length > 0) {
                setTimeout(function () {
                    _fitToGeometry(currentFeatures);
                }, 300);
            }

            _map.renderSync();
            _map.updateSize();

            _updateGeometryField();
            _updateUndoRedoButtons();

            _isUndoRedo = false;

            console.log(`✅ Estado ${index + 1}/${_history.length} restaurado`);
            console.log(
                `  Features na camada: ${_source.getFeatures().length}`,
            );
        }

        function undo() {
            if (_historyIndex > 0) {
                console.log("↩️ Desfazer");
                _restoreFromHistory(_historyIndex - 1);
            } else {
                console.log("⚠️ Sem ações para desfazer");
            }
        }

        function redo() {
            if (_historyIndex < _history.length - 1) {
                console.log("↪️ Refazer");
                _restoreFromHistory(_historyIndex + 1);
            } else {
                console.log("⚠️ Sem ações para refazer");
            }
        }

        function _convertGeometryForSave(geometry) {
            if (!geometry) return null;

            const type = geometry.getType();

            if (type === "GeometryCollection") {
                const geometries = geometry.getGeometries();
                if (geometries && geometries.length > 0) {
                    for (let i = 0; i < geometries.length; i++) {
                        const subGeom = geometries[i];
                        if (subGeom) {
                            return _convertGeometryForSave(subGeom);
                        }
                    }
                }
                return null;
            }

            if (type === "Circle") {
                try {
                    const center = geometry.getCenter();
                    const radius = geometry.getRadius();
                    return new ol.geom.Polygon.fromCircle(
                        new ol.geom.Circle(center, radius),
                        64,
                    );
                } catch (e) {
                    console.error("Erro ao converter círculo:", e);
                    return null;
                }
            }

            return geometry;
        }

        function _generateFullGeoJSON(features) {
            if (!features || features.length === 0) {
                return null;
            }

            try {
                const convertedFeatures = [];
                features.forEach(function (feature) {
                    const geom = feature.getGeometry();
                    if (!geom) return;
                    const convertedGeom = _convertGeometryForSave(geom);
                    if (!convertedGeom) return;
                    const newFeature = new ol.Feature({
                        geometry: convertedGeom,
                    });
                    newFeature.setProperties(feature.getProperties());
                    convertedFeatures.push(newFeature);
                });

                if (convertedFeatures.length === 0) {
                    return null;
                }

                const format = new ol.format.GeoJSON();
                const geojson = format.writeFeatures(convertedFeatures, {
                    dataProjection: "EPSG:4326",
                    featureProjection: "EPSG:3857",
                });

                const geojsonObj = JSON.parse(geojson);
                geojsonObj.crs = {
                    type: "name",
                    properties: {
                        name: "EPSG:4326",
                    },
                };

                return JSON.stringify(geojsonObj);
            } catch (e) {
                console.error("Erro ao gerar GeoJSON:", e);
                return null;
            }
        }

        function _updateGeometryField() {
            const geometryFieldId = _config.geometryFieldId;

            if (!geometryFieldId) {
                console.warn("⚠️ geometryFieldId não definido");
                return;
            }

            console.log("📝 _updateGeometryField");
            if (!_source) return;

            const currentFeatures = _source.getFeatures();

            if (currentFeatures.length > 0) {
                try {
                    const fullGeojson = _generateFullGeoJSON(currentFeatures);
                    if (fullGeojson) {
                        _updateField(geometryFieldId, fullGeojson);
                        console.log(
                            `✅ Geometria atualizada (${currentFeatures.length} features)`,
                        );
                    } else {
                        console.warn(
                            "⚠️ Não foi possível gerar GeoJSON válido",
                        );
                    }
                } catch (e) {
                    console.error("❌ Erro ao atualizar geometria:", e);
                }
            } else {
                _updateField(geometryFieldId, null);
                console.log("✅ Geometria removida (vazio)");
            }

            _updateEditorConfigField();
        }

        function _updateField(fieldId, value) {
            if (!fieldId) return;
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = value;
                field.dispatchEvent(new Event("change", { bubbles: true }));
                console.log(`✅ Campo ${fieldId} atualizado`);
            }
        }

        function _updateEditorConfigField() {
            const editorConfigFieldId = _config.editorConfigFieldId;

            if (!editorConfigFieldId) {
                return;
            }

            const layerNames = Object.keys(_config.layers);
            layerNames.forEach(function (name, index) {
                const olLayer = _olLayers[index];
                if (olLayer) {
                    _editorConfig.layers[name] = {
                        visible: olLayer.getVisible(),
                        opacity: olLayer.getOpacity(),
                    };
                }
            });

            if (_map) {
                const view = _map.getView();
                _editorConfig.map.center = view.getCenter();
                _editorConfig.map.zoom = view.getZoom();
            }

            _editorConfig.ui.layerControlCollapsed = _isLayerControlCollapsed;

            const configJson = JSON.stringify(_editorConfig);
            _updateField(editorConfigFieldId, configJson);
        }

        function _updateUndoRedoButtons() {
            const undoBtn = document.getElementById("editor_undo_btn");
            const redoBtn = document.getElementById("editor_redo_btn");

            if (undoBtn) {
                undoBtn.disabled = _historyIndex <= 0;
                undoBtn.style.opacity = _historyIndex <= 0 ? "0.5" : "1";
            }
            if (redoBtn) {
                redoBtn.disabled = _historyIndex >= _history.length - 1;
                redoBtn.style.opacity =
                    _historyIndex >= _history.length - 1 ? "0.5" : "1";
            }
        }

        function _createDrawInteraction(type, freehand) {
            console.log(`🔄 Criando Draw do tipo: ${type}`);
            console.log(`  Freehand: ${freehand ? "ATIVADO" : "DESATIVADO"}`);

            if (_draw) {
                if (typeof _draw.setActive === "function") {
                    _draw.setActive(false);
                }
                if (_draw._pointListener) {
                    _map.un("click", _draw._pointListener);
                }
                _draw = null;
            }

            if (type === "Point") {
                const pointListener = function (event) {
                    if (!_isDrawingPoint) return;

                    const hit = _map.forEachFeatureAtPixel(
                        event.pixel,
                        function (feature) {
                            return feature;
                        },
                    );
                    if (hit) return;

                    const coord = event.coordinate;
                    _addPointAtCoordinate(coord);
                };

                const fakeDraw = {
                    setActive: function (active) {
                        _isDrawingPoint = active;
                        if (active) {
                            _map.on("click", pointListener);
                            _setDrawCursor(true);
                        } else {
                            _map.un("click", pointListener);
                            _setDrawCursor(false);
                        }
                    },
                    getActive: function () {
                        return _isDrawingPoint;
                    },
                    _pointListener: pointListener,
                };

                fakeDraw.setActive(true);
                _draw = fakeDraw;
                console.log("✅ Draw de ponto criado (clique manual)");
                return _draw;
            }

            const drawOptions = {
                source: _source,
                type: type,
                style: new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "#00ff00",
                        width: 2,
                        lineDash: [4, 4],
                    }),
                    fill: new ol.style.Fill({
                        color: "rgba(0, 255, 0, 0.1)",
                    }),
                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({ color: "#00ff00" }),
                        stroke: new ol.style.Stroke({
                            color: "#ffffff",
                            width: 2,
                        }),
                    }),
                }),
            };

            if (freehand) {
                drawOptions.freehand = true;
                console.log("  ✅ Modo freehand ativado");
            } else {
                drawOptions.freehand = false;
                drawOptions.condition = ol.events.condition.noModifierKeys;
                drawOptions.freehandCondition = function () {
                    return false;
                };
                console.log("  ✅ Modo ponto-a-ponto ativado");
            }

            const draw = new ol.interaction.Draw(drawOptions);

            draw.on("drawend", function () {
                console.log("🟢 DRAWEND");
                _saveToHistory();
                _updateGeometryField();
                _setDrawCursor(false);
            });

            draw.on("drawstart", function () {
                console.log("🟡 DRAWSTART");
                _setDrawCursor(true);
            });

            _map.addInteraction(draw);
            _draw = draw;
            console.log(`✅ Draw criado - Tipo: ${type}`);
            return _draw;
        }

        function _addPointAtCoordinate(coordinate) {
            if (!_source) {
                console.error("❌ Source não disponível");
                return false;
            }

            try {
                const point = new ol.geom.Point(coordinate);
                const feature = new ol.Feature({
                    geometry: point,
                });
                _source.addFeature(feature);
                _source.changed();

                if (_layer) {
                    _layer.changed();
                }
                if (_map) {
                    _map.renderSync();
                }

                console.log("✅ Ponto adicionado com sucesso");
                _saveToHistory();
                _updateGeometryField();
                return true;
            } catch (e) {
                console.error("❌ Erro ao adicionar ponto:", e);
                return false;
            }
        }

        function _setDrawCursor(active) {
            const container = document.getElementById(_config.containerId);
            if (!container) return;

            if (active) {
                if (!_originalCursor) {
                    _originalCursor = container.style.cursor || "default";
                }
                container.style.cursor = "crosshair";
            } else {
                container.style.cursor = _originalCursor || "default";
            }
        }

        function _createLayerStyle() {
            const baseStyle = new ol.style.Style({
                stroke: new ol.style.Stroke({
                    color: "#00ff00",
                    width: 3,
                }),
                fill: new ol.style.Fill({
                    color: "rgba(0, 255, 0, 0.1)",
                }),
            });

            const pointStyle = new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 8,
                    fill: new ol.style.Fill({
                        color: "#00ff00",
                    }),
                    stroke: new ol.style.Stroke({
                        color: "#ffffff",
                        width: 2,
                    }),
                }),
            });

            return function (feature, resolution) {
                const geometry = feature.getGeometry();
                if (!geometry) return baseStyle;

                const type = geometry.getType();

                if (type === "Point" || type === "MultiPoint") {
                    return pointStyle;
                }

                if (type === "Circle") {
                    return new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "#00ff00",
                            width: 3,
                            lineDash: [5, 5],
                        }),
                        fill: new ol.style.Fill({
                            color: "rgba(0, 255, 0, 0.1)",
                        }),
                    });
                }

                return baseStyle;
            };
        }

        function _createLayerControl(olLayers, layerConfigs) {
            const container = document.createElement("div");
            container.id = "layer_control_container";
            container.className = "ol-editor-layer-control";

            const header = document.createElement("div");
            header.id = "layer_control_header";
            header.className = "ol-editor-layer-control-header";

            const leftPart = document.createElement("span");
            leftPart.className = "ol-editor-layer-control-left";
            leftPart.innerHTML =
                '<span class="ol-editor-layer-control-grip"><i class="fas fa-grip-lines"></i></span><span><i class="fas fa-layer-group"></i> Camadas</span>';
            header.appendChild(leftPart);

            const toggleBtn = document.createElement("span");
            toggleBtn.id = "layer_toggle_btn";
            toggleBtn.className = "ol-editor-layer-control-toggle";
            toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
            header.appendChild(toggleBtn);

            container.appendChild(header);

            const body = document.createElement("div");
            body.id = "layer_control_body";
            body.className = "ol-editor-layer-control-body";
            container.appendChild(body);

            const layerNames = Object.keys(layerConfigs);
            if (layerNames.length === 0) {
                const emptyMsg = document.createElement("div");
                emptyMsg.className = "ol-editor-layer-control-empty";
                emptyMsg.textContent = "Nenhuma camada configurada";
                body.appendChild(emptyMsg);
            } else {
                layerNames.forEach(function (name, index) {
                    const config = layerConfigs[name];
                    const olLayer = olLayers[index];

                    if (!olLayer) return;

                    const item = document.createElement("div");
                    item.className = "ol-editor-layer-control-item";

                    const checkbox = document.createElement("input");
                    checkbox.type = "checkbox";
                    checkbox.checked = config.visible !== false;
                    checkbox.className = "ol-editor-layer-control-checkbox";
                    checkbox.id = "layer_chk_" + name;

                    checkbox.onchange = function (e) {
                        const isChecked = this.checked;
                        olLayer.setVisible(isChecked);
                        console.log(
                            `🔄 Camada ${name} ${isChecked ? "ativada" : "desativada"}`,
                        );

                        const opacitySlider = document.getElementById(
                            "layer_opacity_" + name,
                        );
                        if (opacitySlider) {
                            opacitySlider.disabled = !isChecked;
                            opacitySlider.style.opacity = isChecked
                                ? "1"
                                : "0.5";
                        }

                        _updateEditorConfigField();
                    };
                    item.appendChild(checkbox);

                    const label = document.createElement("label");
                    label.htmlFor = "layer_chk_" + name;
                    label.className = "ol-editor-layer-control-label";
                    label.textContent = config.title || name;
                    item.appendChild(label);

                    const opacityContainer = document.createElement("div");
                    opacityContainer.className =
                        "ol-editor-layer-control-opacity";

                    const opacityLabel = document.createElement("span");
                    opacityLabel.className =
                        "ol-editor-layer-control-opacity-label";
                    opacityLabel.textContent =
                        Math.round((config.opacity || 1.0) * 100) + "%";
                    opacityContainer.appendChild(opacityLabel);

                    const opacityInput = document.createElement("input");
                    opacityInput.type = "range";
                    opacityInput.min = "0";
                    opacityInput.max = "100";
                    opacityInput.value = (config.opacity || 1.0) * 100;
                    opacityInput.className =
                        "ol-editor-layer-control-opacity-slider";
                    opacityInput.id = "layer_opacity_" + name;
                    opacityInput.disabled = config.visible === false;
                    opacityInput.style.opacity =
                        config.visible !== false ? "1" : "0.5";

                    opacityInput.oninput = function () {
                        const value = parseInt(this.value) / 100;
                        olLayer.setOpacity(value);
                        const label = this.parentNode.querySelector("span");
                        if (label) {
                            label.textContent = Math.round(value * 100) + "%";
                        }
                    };

                    opacityInput.onchange = function () {
                        _updateEditorConfigField();
                    };
                    opacityContainer.appendChild(opacityInput);

                    item.appendChild(opacityContainer);
                    body.appendChild(item);
                });
            }

            function toggleLayerControl() {
                const bodyEl = document.getElementById("layer_control_body");
                const icon = document.querySelector("#layer_toggle_btn i");
                if (!bodyEl || !icon) return;

                if (bodyEl.style.display === "none") {
                    bodyEl.style.display = "block";
                    icon.className = "fas fa-chevron-up";
                    _isLayerControlCollapsed = false;
                } else {
                    bodyEl.style.display = "none";
                    icon.className = "fas fa-chevron-down";
                    _isLayerControlCollapsed = true;
                }
                _updateEditorConfigField();
            }

            toggleBtn.onclick = function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleLayerControl();
            };

            header.onclick = function (e) {
                if (e.target === toggleBtn || toggleBtn.contains(e.target)) {
                    return;
                }
                toggleLayerControl();
            };

            let isDragging = false;
            let offsetX, offsetY;

            header.addEventListener("mousedown", function (e) {
                if (e.target === toggleBtn || toggleBtn.contains(e.target)) {
                    return;
                }

                isDragging = true;
                const rect = container.getBoundingClientRect();
                offsetX = e.clientX - rect.left;
                offsetY = e.clientY - rect.top;
                container.style.cursor = "grabbing";
                container.style.transition = "none";
                e.preventDefault();
            });

            document.addEventListener("mousemove", function (e) {
                if (!isDragging) return;

                const mapContainer = document.getElementById(
                    _config.containerId,
                );
                if (!mapContainer) return;

                const mapRect = mapContainer.getBoundingClientRect();

                let newX = e.clientX - mapRect.left - offsetX;
                let newY = e.clientY - mapRect.top - offsetY;

                newX = Math.max(
                    0,
                    Math.min(mapRect.width - container.offsetWidth, newX),
                );
                newY = Math.max(
                    0,
                    Math.min(mapRect.height - container.offsetHeight, newY),
                );

                container.style.left = newX + "px";
                container.style.top = newY + "px";
                container.style.bottom = "auto";
                container.style.right = "auto";
            });

            document.addEventListener("mouseup", function () {
                if (isDragging) {
                    isDragging = false;
                    container.style.cursor = "default";
                    container.style.transition = "all 0.1s ease";
                }
            });

            container.style.left = "auto";
            container.style.right = "10px";
            container.style.bottom = "10px";
            container.style.top = "auto";

            return container;
        }

        function _addToolbar(container) {
            const toolbar = document.createElement("div");
            const positionStyles = {
                "top-right": "top:10px;right:10px;bottom:auto;left:auto;",
                "top-left": "top:10px;left:10px;bottom:auto;right:auto;",
                "bottom-right": "bottom:10px;right:10px;top:auto;left:auto;",
                "bottom-left": "bottom:10px;left:10px;top:auto;right:auto;",
            };
            const positionStyle =
                positionStyles[_config.toolbarPosition] ||
                positionStyles["top-right"];

            toolbar.className = "ol-editor-toolbar";
            toolbar.style.cssText =
                "position:absolute;" + positionStyle + "z-index:1000;";

            if (_modify) _modify.setActive(false);
            if (_select) _select.setActive(false);
            if (_draw && typeof _draw.setActive === "function")
                _draw.setActive(false);

            const toolbarButtons = _config.toolbarButtons || {};
            const buttonConfigs = [];

            if (toolbarButtons.select) {
                buttonConfigs.push({
                    key: "select",
                    icon: toolbarButtons.select.icon || "fa-mouse-pointer",
                    label: toolbarButtons.select.label || "Selecionar",
                    hint: toolbarButtons.select.hint || "Selecionar geometria",
                    active: false,
                    action: function () {
                        if (_select) _select.setActive(true);
                        if (_modify) _modify.setActive(false);
                        if (_draw && typeof _draw.setActive === "function")
                            _draw.setActive(false);
                        _setDrawCursor(false);
                        _editorConfig.tools.activeTool = "select";
                        _updateEditorConfigField();
                        console.log("🔍 Selecionar");
                    },
                });
            }

            if (toolbarButtons.draw) {
                buttonConfigs.push({
                    key: "draw",
                    icon: toolbarButtons.draw.icon || "fa-pencil",
                    label: toolbarButtons.draw.label || "Desenhar",
                    hint: toolbarButtons.draw.hint || "Desenhar nova geometria",
                    active: false,
                    hasSubmenu: true,
                });
            }

            if (toolbarButtons.modify) {
                buttonConfigs.push({
                    key: "modify",
                    icon: toolbarButtons.modify.icon || "fa-edit",
                    label: toolbarButtons.modify.label || "Modificar",
                    hint:
                        toolbarButtons.modify.hint ||
                        "Modificar geometria existente",
                    active: false,
                    action: function () {
                        if (_modify) _modify.setActive(true);
                        if (_draw && typeof _draw.setActive === "function")
                            _draw.setActive(false);
                        if (_select) _select.setActive(false);
                        _setDrawCursor(false);
                        _editorConfig.tools.activeTool = "modify";
                        _updateEditorConfigField();
                        console.log("🔧 Modificar");
                    },
                });
            }

            if (toolbarButtons.undo) {
                buttonConfigs.push({
                    key: "undo",
                    icon: toolbarButtons.undo.icon || "fa-undo",
                    label: toolbarButtons.undo.label || "Voltar",
                    hint:
                        toolbarButtons.undo.hint ||
                        "Desfazer última ação (Ctrl+Z)",
                    active: false,
                    isUndo: true,
                    action: function () {
                        undo();
                    },
                });
            }

            if (toolbarButtons.redo) {
                buttonConfigs.push({
                    key: "redo",
                    icon: toolbarButtons.redo.icon || "fa-redo",
                    label: toolbarButtons.redo.label || "Refazer",
                    hint:
                        toolbarButtons.redo.hint ||
                        "Refazer ação desfeita (Ctrl+Y)",
                    active: false,
                    isRedo: true,
                    action: function () {
                        redo();
                    },
                });
            }

            let submenuElement = null;

            buttonConfigs.forEach(function (btn) {
                const b = document.createElement("button");
                b.type =
                    "button"; /* Garantir que o botão não submeta o formulário */
                b.className = "ol-editor-toolbar-btn";

                const iconSpan = document.createElement("span");
                iconSpan.className = "fas " + btn.icon;
                b.appendChild(iconSpan);

                if (_config.showToolbarLabels) {
                    const labelSpan = document.createElement("span");
                    labelSpan.className = "ol-editor-toolbar-btn-label";
                    labelSpan.textContent = btn.label;
                    b.appendChild(labelSpan);
                }

                b.title = btn.hint || btn.label;

                if (btn.isUndo) {
                    b.id = "editor_undo_btn";
                    b.disabled = true;
                    b.style.opacity = "0.5";
                }
                if (btn.isRedo) {
                    b.id = "editor_redo_btn";
                    b.disabled = true;
                    b.style.opacity = "0.5";
                }

                if (btn.hasSubmenu) {
                    b.style.position = "relative";

                    const submenu = document.createElement("div");
                    submenu.className = "ol-editor-toolbar-submenu";
                    submenu.id = "draw_submenu";

                    const drawTypes = [
                        {
                            label: "🔷 Polígono",
                            type: "Polygon",
                            freehand: false,
                            hint: "Desenhar polígono com cliques",
                        },
                        {
                            label: "🔷 Polígono (livre)",
                            type: "Polygon",
                            freehand: true,
                            hint: "Desenhar polígono em modo livre",
                        },
                        {
                            label: "🔶 Linha",
                            type: "LineString",
                            freehand: false,
                            hint: "Desenhar linha com cliques",
                        },
                        {
                            label: "🔶 Linha (livre)",
                            type: "LineString",
                            freehand: true,
                            hint: "Desenhar linha em modo livre",
                        },
                        {
                            label: "⚪ Ponto",
                            type: "Point",
                            freehand: false,
                            hint: "Adicionar ponto",
                        },
                        {
                            label: "🌀 Círculo",
                            type: "Circle",
                            freehand: false,
                            hint: "Desenhar círculo",
                        },
                    ];

                    drawTypes.forEach(function (item) {
                        const opt = document.createElement("div");
                        opt.className = "ol-editor-toolbar-submenu-item";
                        opt.innerHTML = item.label;
                        opt.title = item.hint || item.label;

                        opt.onmouseover = function () {
                            this.style.background = "#e9ecef";
                            this.style.color = "#000";
                        };
                        opt.onmouseout = function () {
                            if (!this.classList.contains("active-type")) {
                                this.style.background = "transparent";
                                this.style.color = "#333";
                            }
                        };
                        opt.onclick = function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log(
                                "🎯 Tipo: " +
                                    item.type +
                                    " | Freehand: " +
                                    item.freehand,
                            );

                            submenu
                                .querySelectorAll(
                                    ".ol-editor-toolbar-submenu-item",
                                )
                                .forEach(function (el) {
                                    el.classList.remove("active-type");
                                    el.style.background = "transparent";
                                    el.style.color = "#333";
                                });
                            this.classList.add("active-type");
                            this.style.background = "#007bff";
                            this.style.color = "white";

                            if (_modify) _modify.setActive(false);
                            if (_select) _select.setActive(false);
                            if (_draw && typeof _draw.setActive === "function")
                                _draw.setActive(false);

                            _draw = _createDrawInteraction(
                                item.type,
                                item.freehand,
                            );
                            if (
                                _draw &&
                                typeof _draw.setActive === "function"
                            ) {
                                _draw.setActive(true);
                            }

                            _editorConfig.tools.activeTool = "draw";
                            _editorConfig.tools.drawType = item.type;
                            _editorConfig.tools.freehand = item.freehand;
                            _updateEditorConfigField();

                            submenu.style.display = "none";
                            _drawSubmenuOpen = false;

                            toolbar
                                .querySelectorAll("button")
                                .forEach(function (btn) {
                                    if (
                                        !btn.id ||
                                        (btn.id !== "editor_undo_btn" &&
                                            btn.id !== "editor_redo_btn")
                                    ) {
                                        btn.style.background = "#f8f9fa";
                                        btn.style.color = "#000";
                                        btn.style.borderColor = "#ccc";
                                        btn.classList.remove("active");
                                    }
                                });
                            b.style.background = "#007bff";
                            b.style.color = "white";
                            b.style.borderColor = "#007bff";
                            b.classList.add("active");

                            console.log(
                                "✅ Desenhar - Tipo: " +
                                    item.type +
                                    " | Freehand: " +
                                    item.freehand,
                            );
                        };
                        submenu.appendChild(opt);
                    });

                    b.appendChild(submenu);
                    submenuElement = submenu;

                    b.onclick = function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log("📋 Alternando submenu");

                        if (submenu.style.display === "block") {
                            submenu.style.display = "none";
                            _drawSubmenuOpen = false;
                        } else {
                            document
                                .querySelectorAll("#draw_submenu")
                                .forEach(function (el) {
                                    el.style.display = "none";
                                });
                            submenu.style.display = "block";
                            _drawSubmenuOpen = true;
                        }
                    };
                } else {
                    b.onclick = function (e) {
                        e.preventDefault();
                        e.stopPropagation();

                        if (this.disabled) return;

                        toolbar
                            .querySelectorAll("button")
                            .forEach(function (btn) {
                                if (
                                    !btn.id ||
                                    (btn.id !== "editor_undo_btn" &&
                                        btn.id !== "editor_redo_btn")
                                ) {
                                    btn.style.background = "#f8f9fa";
                                    btn.style.color = "#000";
                                    btn.style.borderColor = "#ccc";
                                    btn.classList.remove("active");
                                }
                            });
                        this.style.background = "#007bff";
                        this.style.color = "white";
                        this.style.borderColor = "#007bff";
                        this.classList.add("active");

                        if (submenuElement) {
                            submenuElement.style.display = "none";
                            _drawSubmenuOpen = false;
                        }

                        if (btn.action) btn.action();
                    };
                }

                b.onmouseover = function () {
                    if (!this.disabled && !this.classList.contains("active")) {
                        this.style.background = "#e9ecef";
                    }
                };
                b.onmouseout = function () {
                    if (!this.classList.contains("active") && !this.disabled) {
                        this.style.background = "#f8f9fa";
                    }
                };

                if (btn.active) {
                    b.style.background = "#007bff";
                    b.style.color = "white";
                    b.style.borderColor = "#007bff";
                    b.classList.add("active");
                }
                toolbar.appendChild(b);
            });

            document.addEventListener("click", function (e) {
                if (
                    submenuElement &&
                    !submenuElement.parentNode.contains(e.target)
                ) {
                    submenuElement.style.display = "none";
                    _drawSubmenuOpen = false;
                }
            });

            container.appendChild(toolbar);
            console.log(
                "✅ Toolbar adicionada (labels: " +
                    (_config.showToolbarLabels ? "SIM" : "NÃO") +
                    ")",
            );

            _updateUndoRedoButtons();
        }

        function _addInstructions(container) {
            const div = document.createElement("div");
            div.className = "ol-editor-instructions";

            const freehandText = _config.freehand ? " (modo livre)" : "";
            div.innerHTML =
                "🖱️ Clique → desenhar" +
                freehandText +
                " | 🔄 Arraste → mover | ❌ Clique no vertice → deletar | ✏️ Duplo clique → inserir vertice | ↩️ Ctrl+Z → desfazer | ↪️ Ctrl+Y → refazer";
            container.appendChild(div);

            setTimeout(function () {
                if (div.parentNode) {
                    div.style.opacity = "0";
                    div.style.transition = "opacity 1s";
                    setTimeout(function () {
                        if (div.parentNode) div.parentNode.removeChild(div);
                    }, 1000);
                }
            }, 12000);
        }

        /* ======================================== */
        /* FUNÇÕES PÚBLICAS                        */
        /* ======================================== */
        function getMap() {
            return _map;
        }

        function getSource() {
            return _source;
        }

        function getLayer() {
            return _layer;
        }

        function setGeometry(geom) {
            _config.geom = geom;
            if (_initialized) {
                const features = _prepareFeatures(geom);
                _source.clear();
                _source.addFeatures(features);
                _source.changed();

                if (features && features.length > 0) {
                    setTimeout(function () {
                        _fitToGeometry(features);
                    }, 300);
                }

                _updateGeometryField();
                _saveToHistory();
            }
        }

        function restoreEditorConfig(configData) {
            if (!configData) {
                console.log("⚠️ Nenhum dado de configuração para restaurar");
                return;
            }

            console.log("📌 Restaurando configurações do editor");

            try {
                const config =
                    typeof configData === "string"
                        ? JSON.parse(configData)
                        : configData;

                if (config.layers) {
                    Object.keys(config.layers).forEach(function (name) {
                        const settings = config.layers[name];
                        const index = Object.keys(_config.layers).indexOf(name);

                        if (index !== -1 && _olLayers[index]) {
                            const olLayer = _olLayers[index];
                            if (settings.visible !== undefined) {
                                olLayer.setVisible(settings.visible);
                                const checkbox = document.getElementById(
                                    "layer_chk_" + name,
                                );
                                if (checkbox) {
                                    checkbox.checked = settings.visible;
                                }
                            }
                            if (settings.opacity !== undefined) {
                                olLayer.setOpacity(settings.opacity);
                                const slider = document.getElementById(
                                    "layer_opacity_" + name,
                                );
                                if (slider) {
                                    slider.value = Math.round(
                                        settings.opacity * 100,
                                    );
                                    const label =
                                        slider.parentNode.querySelector("span");
                                    if (label) {
                                        label.textContent =
                                            Math.round(settings.opacity * 100) +
                                            "%";
                                    }
                                }
                            }
                        }
                    });
                }

                if (config.ui) {
                    if (config.ui.layerControlCollapsed !== undefined) {
                        _isLayerControlCollapsed =
                            config.ui.layerControlCollapsed;
                        const bodyEl =
                            document.getElementById("layer_control_body");
                        const icon = document.querySelector(
                            "#layer_toggle_btn i",
                        );
                        if (bodyEl && icon) {
                            if (_isLayerControlCollapsed) {
                                bodyEl.style.display = "none";
                                icon.className = "fas fa-chevron-down";
                            } else {
                                bodyEl.style.display = "block";
                                icon.className = "fas fa-chevron-up";
                            }
                        }
                    }
                }

                if (config.tools && config.tools.activeTool) {
                    const toolName = config.tools.activeTool;
                    console.log("  🔧 Restaurando ferramenta: " + toolName);
                    const event = new CustomEvent("restoreTool", {
                        detail: { tool: toolName },
                    });
                    document.dispatchEvent(event);
                }

                setTimeout(function () {
                    _updateEditorConfigField();
                }, 100);

                console.log("✅ Configurações restauradas");
            } catch (e) {
                console.error("❌ Erro ao restaurar configurações:", e);
            }
        }

        /* ======================================== */
        /* API PÚBLICA                             */
        /* ======================================== */
        return {
            init: function (config) {
                _initEditor(config);
                return this;
            },

            getMap: getMap,
            getSource: getSource,
            getLayer: getLayer,
            setGeometry: setGeometry,
            restoreConfig: restoreEditorConfig,
        };
    })();

    /* Garante que GeoMapEditorApp está disponível globalmente */
    if (typeof GeoMapEditorApp !== "undefined") {
        window.GeoMapEditorApp = GeoMapEditorApp;
    } else {
        console.error("Falha ao inicializar GeoMapEditorApp");
    }

    console.log("✅ GeoMapEditorApp carregado");
})();
