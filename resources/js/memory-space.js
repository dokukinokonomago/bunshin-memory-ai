import * as THREE from 'three';

const SHARED_API_CONFIG_STORAGE_KEY = 'bunshin-admin-api-config';
const LOCAL_DEV_TOKEN = 'local-dev-token';

const root = document.getElementById('memory-space-root');

if (root) {
    const els = {
        controlsToggle: document.getElementById('controls-toggle'),
        listToggle: document.getElementById('list-toggle'),
        controls: document.getElementById('memory-space-controls'),
        loginForm: document.getElementById('login-form'),
        loginEmail: document.getElementById('login-email'),
        loginPassword: document.getElementById('login-password'),
        loginSubmit: document.getElementById('login-submit'),
        loginStatus: document.getElementById('login-status'),
        apiBase: document.getElementById('api-base'),
        apiToken: document.getElementById('api-token'),
        period: document.getElementById('period-filter'),
        category: document.getElementById('category-filter'),
        includeDescendants: document.getElementById('include-descendants'),
        load: document.getElementById('load-space'),
        unlock: document.getElementById('unlock-secret'),
        status: document.getElementById('space-status'),
        metricCategoryCount: document.getElementById('metric-category-count'),
        metricMemoryCount: document.getElementById('metric-memory-count'),
        metricSecretCount: document.getElementById('metric-secret-count'),
        list: document.getElementById('memory-list'),
        detail: document.getElementById('memory-detail'),
        detailClose: document.getElementById('detail-close'),
        detailCrumb: document.getElementById('detail-crumb'),
        detailTitle: document.getElementById('detail-title'),
        detailBody: document.getElementById('detail-body'),
        detailEmotions: document.getElementById('detail-emotions'),
        detailBeliefs: document.getElementById('detail-beliefs'),
        detailTags: document.getElementById('detail-tags'),
        unlockDialog: document.getElementById('unlock-dialog'),
        unlockForm: document.getElementById('unlock-form'),
        unlockPassword: document.getElementById('unlock-password'),
        unlockCancel: document.getElementById('unlock-cancel'),
        unlockError: document.getElementById('unlock-error'),
    };

    const canvas = document.getElementById('memory-space-canvas');
    const graphics = createGraphics(canvas);
    const { renderer, scene, camera, raycaster, pointer, clock } = graphics;

    const rootGroups = [];
    const childGroups = [];
    const memoryGroups = [];
    const clickable = [];
    const dynamicObjects = [];
    const categoryMap = new Map();
    const categoryPositionMap = new Map();
    const categoryColorMap = new Map();

    const state = {
        apiBase: root.dataset.apiBase || '/api/v1',
        token: '',
        tokenSource: 'manual',
        periodKey: '',
        categoryId: '',
        includeDescendants: true,
        unlockToken: '',
        unlockExpiresAt: null,
        categories: [],
        memories: [],
        periods: [],
        secret: { locked: false, locked_count: 0, unlock_expires_at: null },
        activeMemoryId: null,
        panels: {
            controls: false,
            list: false,
        },
    };

    const palette = [
        0xfbbf24,
        0x60a5fa,
        0xf472b6,
        0x34d399,
        0xfb923c,
        0xa78bfa,
        0x2dd4bf,
        0xf43f5e,
    ];

    const emotionColors = {
        喜び: '#fbbf24',
        感動: '#f472b6',
        愛: '#f472b6',
        驚き: '#60a5fa',
        興奮: '#60a5fa',
        懐かしさ: '#a78bfa',
        安心: '#34d399',
        癒し: '#34d399',
        切なさ: '#fb923c',
        楽しさ: '#fbbf24',
        ドキドキ: '#f472b6',
        達成感: '#34d399',
        幸せ: '#fbbf24',
        緊張: '#fb923c',
        ワクワク: '#60a5fa',
        憧れ: '#a78bfa',
    };

    const cameraTarget = new THREE.Vector3(0, 0, 0);
    const cameraTargetGoal = new THREE.Vector3(0, 0, 0);
    const spherical = { theta: 0, phi: Math.PI / 2, radius: 3800 };
    const sphericalGoal = { ...spherical };

    let dragging = false;
    let rightDragging = false;
    let previousPointer = { x: 0, y: 0 };
    let pointerDownPosition = { x: 0, y: 0 };
    let flyAnimation = null;

    if (isWebglAvailable()) {
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setClearColor(0x030406, 1);
        scene.fog = new THREE.FogExp2(0x030406, 0.00034);
        raycaster.params.Mesh.threshold = 5;
        addStarField();
        resize();
    } else {
        root.classList.add('is-webgl-unavailable');
        canvas.setAttribute('aria-hidden', 'true');
    }

    bindEvents();
    applySavedApiConfig();
    renderList();
    renderMetrics();

    if (isWebglAvailable()) {
        animate();
    } else {
        setStatus(webglUnavailableMessage(), 'error');
    }

    if (state.token) {
        loadMemorySpace();
    }

    function createGraphics(targetCanvas) {
        try {
            const webglRenderer = new THREE.WebGLRenderer({
                canvas: targetCanvas,
                antialias: true,
                alpha: false,
                preserveDrawingBuffer: true,
            });

            return {
                renderer: webglRenderer,
                scene: new THREE.Scene(),
                camera: new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 1, 24000),
                raycaster: new THREE.Raycaster(),
                pointer: new THREE.Vector2(),
                clock: new THREE.Clock(),
            };
        } catch (error) {
            console.warn('Memory space WebGL initialization failed; using list fallback.', error);

            return {
                renderer: null,
                scene: null,
                camera: null,
                raycaster: null,
                pointer: null,
                clock: null,
            };
        }
    }

    function bindEvents() {
        els.controlsToggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            togglePanel('controls');
        });
        els.listToggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            togglePanel('list');
        });
        els.load.addEventListener('click', () => loadMemorySpace());
        els.period.addEventListener('change', () => loadMemorySpace());
        els.category.addEventListener('change', () => loadMemorySpace());
        els.includeDescendants.addEventListener('change', () => loadMemorySpace());
        els.loginForm.addEventListener('submit', submitLogin);
        els.loginEmail.addEventListener('input', clearLoginStatus);
        els.loginPassword.addEventListener('input', clearLoginStatus);
        els.unlock.addEventListener('click', openUnlockDialog);
        els.unlockCancel.addEventListener('click', closeUnlockDialog);
        els.unlockForm.addEventListener('submit', submitUnlock);
        els.detailClose.addEventListener('click', hideDetail);

        if (isWebglAvailable()) {
            canvas.addEventListener('pointerdown', onPointerDown);
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', onPointerUp);
            canvas.addEventListener('click', onCanvasClick);
            canvas.addEventListener('dblclick', onCanvasDoubleClick);
            canvas.addEventListener('contextmenu', (event) => event.preventDefault());
            canvas.addEventListener('wheel', onWheel, { passive: false });
            window.addEventListener('resize', resize);
        }
    }

    function togglePanel(panel) {
        setPanelOpen(panel, !state.panels[panel]);
    }

    function setPanelOpen(panel, open) {
        state.panels[panel] = open;

        if (panel === 'controls') {
            els.controls.hidden = !open;
            els.controlsToggle.setAttribute('aria-expanded', String(open));
            els.controlsToggle.classList.toggle('is-open', open);
            root.classList.toggle('is-controls-open', open);
        }

        if (panel === 'list') {
            els.list.hidden = !open;
            els.listToggle.setAttribute('aria-expanded', String(open));
            els.listToggle.classList.toggle('is-open', open);
            root.classList.toggle('is-list-open', open);
        }
    }

    async function loadMemorySpace() {
        readControls();
        saveSharedApiConfig();

        if (!state.token) {
            setStatus('Bearer token が必要です。', 'error');
            return;
        }

        setControlsDisabled(true);
        setStatus('同期中...', '');

        try {
            const query = new URLSearchParams();

            if (state.periodKey) {
                query.set('period_key', state.periodKey);
            }

            if (state.categoryId) {
                query.set('category_id', state.categoryId);
                query.set('include_descendants', state.includeDescendants ? '1' : '0');
            }

            if (state.unlockToken) {
                query.set('include_secret', '1');
            }

            const response = await apiFetch(`/memory-space${query.toString() ? `?${query.toString()}` : ''}`, {
                headers: state.unlockToken ? { 'X-Secret-Unlock': state.unlockToken } : {},
            });

            const payload = response.data || {};

            state.categories = Array.isArray(payload.categories) ? payload.categories : [];
            state.memories = Array.isArray(payload.memories) ? payload.memories : [];
            state.periods = Array.isArray(payload.periods) ? payload.periods : [];
            state.secret = payload.secret || state.secret;
            state.unlockExpiresAt = state.secret.unlock_expires_at || state.unlockExpiresAt;

            syncFilterOptions();
            rebuildScene();
            renderList();
            renderMetrics();
            hideDetail();
            setStatus(statusMessage(), 'ok');
        } catch (error) {
            if (error.status === 401) {
                handleAuthenticationRequired(error.message);
            } else {
                setStatus(error.message, 'error');
            }
        } finally {
            setControlsDisabled(false);
        }
    }

    async function submitLogin(event) {
        event.preventDefault();
        readControls();
        clearLoginStatus();

        const email = els.loginEmail.value.trim();
        const password = els.loginPassword.value;

        if (!email || !password) {
            setLoginStatus('Email と password が必要です。', 'error');
            return;
        }

        setAuthControlsDisabled(true);
        setLoginStatus('ログイン中...', '');

        try {
            const response = await apiFetch('/auth/login', {
                method: 'POST',
                auth: false,
                body: JSON.stringify({ email, password }),
            });
            const token = response.data?.access_token || '';

            if (!token) {
                throw new Error('Login response に access token がありません。');
            }

            applyToken(token, 'login');
            state.unlockToken = '';
            state.unlockExpiresAt = null;
            els.loginPassword.value = '';
            setLoginStatus(loginSuccessMessage(response.data), 'ok');
            await loadMemorySpace();
        } catch (error) {
            setLoginStatus(error.message, 'error');
        } finally {
            setAuthControlsDisabled(false);
        }
    }

    async function submitUnlock(event) {
        event.preventDefault();
        readControls();
        clearUnlockError();

        if (!state.token) {
            setUnlockError('Bearer token が必要です。');
            return;
        }

        const password = els.unlockPassword.value;

        if (!password) {
            setUnlockError('Password が必要です。');
            return;
        }

        try {
            const response = await apiFetch('/secret-unlocks', {
                method: 'POST',
                body: JSON.stringify({ password }),
            });

            state.unlockToken = response.data?.unlock_token || '';
            state.unlockExpiresAt = response.data?.expires_at || null;
            els.unlockPassword.value = '';
            closeUnlockDialog();
            await loadMemorySpace();
        } catch (error) {
            if (error.status === 401) {
                handleAuthenticationRequired(error.message);
            }

            setUnlockError(error.message);
        }
    }

    async function apiFetch(path, options = {}) {
        const apiBase = trimTrailingSlash(state.apiBase || '/api/v1');
        const { auth = true, ...fetchOptions } = options;
        const headers = new Headers(options.headers || {});

        headers.set('Accept', 'application/json');

        if (auth) {
            headers.set('Authorization', authorizationHeader(state.token));
        }

        if (options.body) {
            headers.set('Content-Type', 'application/json');
        }

        const response = await fetch(`${apiBase}${path}`, {
            ...fetchOptions,
            headers,
        });

        const text = await response.text();
        let payload = null;

        if (text) {
            try {
                payload = JSON.parse(text);
            } catch {
                payload = null;
            }
        }

        if (!response.ok) {
            const error = new Error(errorMessage(response, payload));
            error.status = response.status;
            error.payload = payload;

            throw error;
        }

        return payload || {};
    }

    function errorMessage(response, payload) {
        if (payload?.message) {
            if (payload.errors && typeof payload.errors === 'object') {
                const firstError = Object.values(payload.errors).flat()[0];

                return firstError ? `${payload.message}: ${firstError}` : payload.message;
            }

            return payload.message;
        }

        if (response.status === 401) {
            return '401 Unauthorized';
        }

        if (response.status === 422) {
            return '422 Validation error';
        }

        return `${response.status} ${response.statusText}`;
    }

    function readControls() {
        state.apiBase = trimTrailingSlash(els.apiBase.value.trim() || '/api/v1');
        const nextToken = els.apiToken.value.trim();

        if (nextToken !== state.token) {
            state.tokenSource = 'manual';
        }

        state.token = nextToken;
        state.periodKey = els.period.value;
        state.categoryId = els.category.value;
        state.includeDescendants = els.includeDescendants.checked;
    }

    function applySavedApiConfig() {
        const saved = loadSharedApiConfig();

        if (saved.baseUrl) {
            state.apiBase = trimTrailingSlash(saved.baseUrl);
            els.apiBase.value = state.apiBase;
        }

        if (saved.token) {
            applyToken(saved.token, saved.tokenSource, false);
        }
    }

    function loadSharedApiConfig() {
        try {
            const saved = JSON.parse(localStorage.getItem(SHARED_API_CONFIG_STORAGE_KEY) || '{}');

            const savedToken = typeof saved.token === 'string' ? saved.token.trim() : '';
            const savedTokenSource = typeof saved.token_source === 'string' ? saved.token_source : 'manual';

            return {
                baseUrl: typeof saved.baseUrl === 'string' ? saved.baseUrl.trim() : '',
                ...defaultLocalDevToken(savedToken, savedTokenSource),
            };
        } catch {
            return { baseUrl: '', ...defaultLocalDevToken('', 'manual') };
        }
    }

    function defaultLocalDevToken(savedToken, tokenSource) {
        if (!isLocalDevHost()) {
            return { token: savedToken, tokenSource };
        }

        if (!savedToken || (savedToken.includes('|') && tokenSource !== 'login')) {
            return { token: LOCAL_DEV_TOKEN, tokenSource: 'local-dev' };
        }

        return { token: savedToken, tokenSource };
    }

    function isLocalDevHost() {
        return ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
    }

    function saveSharedApiConfig() {
        try {
            localStorage.setItem(SHARED_API_CONFIG_STORAGE_KEY, JSON.stringify({
                baseUrl: state.apiBase || '/api/v1',
                token: state.token || '',
                token_source: state.tokenSource || 'manual',
            }));
        } catch {
            // localStorage can be unavailable in restricted browser contexts.
        }
    }

    function applyToken(token, tokenSource = 'manual', save = true) {
        state.token = token;
        state.tokenSource = tokenSource;
        els.apiToken.value = token;

        if (save) {
            saveSharedApiConfig();
        }
    }

    function resourceId(resource) {
        return resource?.public_id || resource?.id || '';
    }

    function memoryCategoryId(memory) {
        return memory?.category_public_id || memory?.category_id || '';
    }

    function syncFilterOptions() {
        const selectedPeriod = els.period.value;
        const selectedCategory = els.category.value;

        replaceOptions(
            els.period,
            [{ value: '', label: 'すべて' }, ...state.periods.map((period) => ({
                value: period.key,
                label: period.label,
            }))],
            selectedPeriod,
        );

        const categoryOptions = flattenCategories(state.categories).map((category) => ({
            value: String(resourceId(category)),
            label: `${'　'.repeat(category.depth)}${category.name}`,
        }));

        replaceOptions(
            els.category,
            [{ value: '', label: 'すべて' }, ...categoryOptions],
            selectedCategory,
        );
    }

    function replaceOptions(select, options, selectedValue) {
        select.replaceChildren();

        for (const option of options) {
            const element = document.createElement('option');
            element.value = option.value;
            element.textContent = option.label;
            select.append(element);
        }

        select.value = options.some((option) => option.value === selectedValue) ? selectedValue : '';
    }

    function rebuildScene() {
        clearDynamicObjects();
        categoryMap.clear();
        categoryPositionMap.clear();
        categoryColorMap.clear();

        const roots = state.categories.length > 0
            ? state.categories
            : syntheticRootsForUncategorizedMemories();

        const flattened = flattenCategories(roots);

        for (const category of flattened) {
            categoryMap.set(String(resourceId(category)), category);
        }

        if (!isWebglAvailable()) {
            return;
        }

        roots.forEach((category, index) => {
            const color = palette[index % palette.length];
            const position = rootPosition(index, roots.length);
            categoryPositionMap.set(String(resourceId(category)), position);
            categoryColorMap.set(String(resourceId(category)), color);

            const group = makeRootCategory(category, color, position);
            rootGroups.push(group);
            dynamicObjects.push(group.group);
            scene.add(group.group);

            const children = Array.isArray(category.children) ? category.children : [];
            children.forEach((child, childIndex) => {
                addChildCategory(child, category, color, position, childIndex, children.length);
            });
        });

        addMemories();
        resetCameraForData();
    }

    function addChildCategory(category, parent, color, parentPosition, index, total) {
        const relative = childRelativePosition(index, total);
        const position = new THREE.Vector3(
            parentPosition.x + relative.x,
            parentPosition.y + relative.y,
            parentPosition.z + relative.z,
        );

        categoryPositionMap.set(String(resourceId(category)), position);
        categoryColorMap.set(String(resourceId(category)), color);

        const group = makeChildCategory(category, parent, color, position);
        childGroups.push(group);
        dynamicObjects.push(group.group);
        scene.add(group.group);

        const children = Array.isArray(category.children) ? category.children : [];
        children.forEach((child, childIndex) => {
            addChildCategory(child, category, color, position, childIndex, children.length);
        });
    }

    function addMemories() {
        const memoryBuckets = new Map();

        for (const memory of state.memories) {
            const categoryId = memoryCategoryId(memory) ? String(memoryCategoryId(memory)) : 'uncategorized';
            const bucket = memoryBuckets.get(categoryId) || [];
            bucket.push(memory);
            memoryBuckets.set(categoryId, bucket);
        }

        for (const [categoryId, bucket] of memoryBuckets.entries()) {
            const basePosition = categoryPositionMap.get(categoryId) || new THREE.Vector3(0, -280, 0);
            const color = categoryColorMap.get(categoryId) || palette[0];
            const category = categoryMap.get(categoryId) || {
                public_id: '',
                id: 0,
                name: '未分類',
                parentName: '記憶',
            };

            bucket.forEach((memory, index) => {
                const position = memoryPosition(basePosition, index, bucket.length);
                const group = makeMemory(memory, category, color, position);
                memoryGroups.push(group);
                dynamicObjects.push(group.group);
                scene.add(group.group);
            });
        }
    }

    function makeRootCategory(category, color, position) {
        const group = new THREE.Group();
        group.position.copy(position);

        const hex = `#${new THREE.Color(color).getHexString()}`;
        const glow = makeGlowSprite(hex, 900);
        group.add(glow);

        const mesh = new THREE.Mesh(
            new THREE.OctahedronGeometry(74, 0),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.24 }),
        );
        group.add(mesh);

        const wire = new THREE.Mesh(
            new THREE.OctahedronGeometry(74, 0),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.58, wireframe: true }),
        );
        group.add(wire);

        addRings(group, color, [
            [138, 2.2, 0.34, Math.PI / 3, 0],
            [108, 1.4, 0.22, Math.PI / 6, Math.PI / 4],
            [162, 0.9, 0.16, Math.PI * 0.42, Math.PI / 2],
        ]);

        const label = makeTextSprite(category.name, 34, hex);
        label.scale.set(230, 58, 1);
        label.position.set(0, -136, 0);
        group.add(label);

        saveBaseOpacity(group);
        clickable.push({ type: 'category', mesh, category });

        return { group, mesh, wire, category };
    }

    function makeChildCategory(category, parent, color, position) {
        const group = new THREE.Group();
        group.position.copy(position);

        const hex = `#${new THREE.Color(color).getHexString()}`;
        const glow = makeGlowSprite(hex, 320);
        group.add(glow);

        const mesh = new THREE.Mesh(
            new THREE.IcosahedronGeometry(28, 0),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.24 }),
        );
        group.add(mesh);

        const wire = new THREE.Mesh(
            new THREE.IcosahedronGeometry(28, 0),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.55, wireframe: true }),
        );
        group.add(wire);

        addRings(group, color, [[44, 1.1, 0.36, Math.PI / 2.5, 0]]);

        const label = makeTextSprite(category.name, 23, hex);
        label.scale.set(180, 42, 1);
        label.position.set(0, -48, 0);
        group.add(label);

        saveBaseOpacity(group);
        clickable.push({ type: 'category', mesh, category: { ...category, parentName: parent.name } });

        return { group, mesh, wire, category };
    }

    function makeMemory(memory, category, categoryColor, position) {
        const group = new THREE.Group();
        group.position.copy(position);

        const topEmotion = dominantEmotion(memory);
        const colorHex = emotionColors[topEmotion] || `#${new THREE.Color(categoryColor).getHexString()}`;
        const color = new THREE.Color(colorHex);
        const importance = importanceScore(memory);
        const radius = 9 + importance * 18;

        const glow = makeGlowSprite(colorHex, radius * 7);
        group.add(glow);

        const mesh = new THREE.Mesh(
            new THREE.SphereGeometry(radius, 24, 24),
            new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.68 }),
        );
        group.add(mesh);

        if (strongestEmotionScore(memory) >= 85 || memory.visibility === 'secret') {
            const ring = new THREE.Mesh(
                new THREE.TorusGeometry(radius + 4, 0.75, 6, 32),
                new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.26 }),
            );
            ring.rotation.x = Math.PI / 2;
            group.add(ring);
        }

        const label = makeTextSprite(truncate(memory.title || 'Untitled', 16), 16, colorHex);
        label.scale.set(172, 30, 1);
        label.position.set(0, -(radius + 18), 0);
        label.visible = false;
        group.add(label);

        saveBaseOpacity(group);
        clickable.push({ type: 'memory', mesh, memory, category });

        return { group, mesh, label, memory };
    }

    function addRings(group, color, specs) {
        for (const [radius, tube, opacity, rotationX, rotationZ] of specs) {
            const ring = new THREE.Mesh(
                new THREE.TorusGeometry(radius, tube, 6, 80),
                new THREE.MeshBasicMaterial({ color, transparent: true, opacity }),
            );
            ring.rotation.x = rotationX;
            ring.rotation.z = rotationZ;
            group.add(ring);
        }
    }

    function makeGlowSprite(colorHex, size) {
        const color = new THREE.Color(colorHex);
        const red = Math.round(color.r * 255);
        const green = Math.round(color.g * 255);
        const blue = Math.round(color.b * 255);
        const spriteCanvas = document.createElement('canvas');
        const spriteSize = 128;
        spriteCanvas.width = spriteSize;
        spriteCanvas.height = spriteSize;

        const context = spriteCanvas.getContext('2d');
        const gradient = context.createRadialGradient(
            spriteSize / 2,
            spriteSize / 2,
            0,
            spriteSize / 2,
            spriteSize / 2,
            spriteSize / 2,
        );

        gradient.addColorStop(0, `rgba(${red}, ${green}, ${blue}, 0.56)`);
        gradient.addColorStop(0.42, `rgba(${red}, ${green}, ${blue}, 0.16)`);
        gradient.addColorStop(1, `rgba(${red}, ${green}, ${blue}, 0)`);
        context.fillStyle = gradient;
        context.fillRect(0, 0, spriteSize, spriteSize);

        const texture = new THREE.CanvasTexture(spriteCanvas);
        const material = new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending,
        });
        const sprite = new THREE.Sprite(material);
        sprite.scale.set(size, size, 1);

        return sprite;
    }

    function makeTextSprite(text, fontSize, color) {
        const labelCanvas = document.createElement('canvas');
        labelCanvas.width = 512;
        labelCanvas.height = 96;

        const context = labelCanvas.getContext('2d');
        context.clearRect(0, 0, labelCanvas.width, labelCanvas.height);
        context.font = `600 ${fontSize}px sans-serif`;
        context.fillStyle = color;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.shadowColor = 'rgba(0, 0, 0, 0.7)';
        context.shadowBlur = 8;
        context.fillText(text, labelCanvas.width / 2, labelCanvas.height / 2);

        const texture = new THREE.CanvasTexture(labelCanvas);
        const material = new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthWrite: false,
        });

        return new THREE.Sprite(material);
    }

    function addStarField() {
        const starCount = 3600;
        const positions = new Float32Array(starCount * 3);

        for (let i = 0; i < starCount; i += 1) {
            const radius = 3000 + Math.random() * 9000;
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos((Math.random() * 2) - 1);

            positions[i * 3] = radius * Math.sin(phi) * Math.cos(theta);
            positions[(i * 3) + 1] = radius * Math.cos(phi);
            positions[(i * 3) + 2] = radius * Math.sin(phi) * Math.sin(theta);
        }

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        const material = new THREE.PointsMaterial({
            color: 0xb8e1ff,
            size: 1.3,
            transparent: true,
            opacity: 0.56,
        });

        scene.add(new THREE.Points(geometry, material));
    }

    function onPointerDown(event) {
        flyAnimation = null;
        dragging = true;
        rightDragging = event.button === 2 || event.shiftKey;
        previousPointer = { x: event.clientX, y: event.clientY };
        pointerDownPosition = previousPointer;
        canvas.setPointerCapture?.(event.pointerId);
    }

    function onPointerMove(event) {
        if (!dragging) {
            return;
        }

        const deltaX = event.clientX - previousPointer.x;
        const deltaY = event.clientY - previousPointer.y;
        previousPointer = { x: event.clientX, y: event.clientY };

        if (rightDragging) {
            const right = new THREE.Vector3();
            right.crossVectors(camera.getWorldDirection(new THREE.Vector3()), camera.up).normalize();
            const up = camera.up.clone().normalize();
            const panSpeed = spherical.radius * 0.001;
            cameraTarget.addScaledVector(right, -deltaX * panSpeed);
            cameraTarget.addScaledVector(up, deltaY * panSpeed);
            cameraTargetGoal.copy(cameraTarget);

            return;
        }

        sphericalGoal.theta -= deltaX * 0.004;
        sphericalGoal.phi = Math.max(0.12, Math.min(Math.PI - 0.12, sphericalGoal.phi - (deltaY * 0.004)));
    }

    function onPointerUp() {
        dragging = false;
    }

    function onWheel(event) {
        event.preventDefault();
        const factor = event.deltaY > 0 ? 1.08 : 0.92;
        sphericalGoal.radius = Math.max(80, Math.min(8200, sphericalGoal.radius * factor));
    }

    function onCanvasClick(event) {
        if (Math.abs(event.clientX - pointerDownPosition.x) > 5 || Math.abs(event.clientY - pointerDownPosition.y) > 5) {
            return;
        }

        const hit = pickObject(event.clientX, event.clientY);

        if (!hit) {
            hideDetail();
            return;
        }

        if (hit.type === 'memory') {
            showMemoryDetail(hit.memory, hit.category);
            return;
        }

        showCategoryDetail(hit.category);
    }

    function onCanvasDoubleClick(event) {
        const hit = pickObject(event.clientX, event.clientY);

        if (!hit?.mesh?.parent) {
            return;
        }

        const position = new THREE.Vector3();
        hit.mesh.parent.getWorldPosition(position);
        flyTo(position, hit.type === 'memory' ? 120 : 520);
    }

    function pickObject(clientX, clientY) {
        if (!isWebglAvailable()) {
            return null;
        }

        const rect = canvas.getBoundingClientRect();
        pointer.x = ((clientX - rect.left) / rect.width) * 2 - 1;
        pointer.y = -(((clientY - rect.top) / rect.height) * 2 - 1);
        raycaster.setFromCamera(pointer, camera);

        const meshes = clickable
            .filter((item) => item.mesh.parent && item.mesh.parent.visible !== false)
            .map((item) => item.mesh);
        const hits = raycaster.intersectObjects(meshes);

        if (hits.length === 0) {
            return null;
        }

        return clickable.find((item) => item.mesh === hits[0].object) || null;
    }

    function flyTo(position, radius) {
        flyAnimation = {
            fromTarget: cameraTarget.clone(),
            toTarget: position.clone(),
            fromRadius: spherical.radius,
            toRadius: radius,
            progress: 0,
            duration: 80,
        };
    }

    function animate() {
        if (!isWebglAvailable()) {
            return;
        }

        requestAnimationFrame(animate);

        const elapsed = clock.getElapsedTime();
        stepFlyAnimation();

        spherical.radius += (sphericalGoal.radius - spherical.radius) * 0.1;
        spherical.theta += (sphericalGoal.theta - spherical.theta) * 0.1;
        spherical.phi += (sphericalGoal.phi - spherical.phi) * 0.1;
        cameraTarget.lerp(cameraTargetGoal, 0.12);

        const cameraPosition = sphericalToCartesian(spherical);
        camera.position.copy(cameraTarget).add(cameraPosition);
        camera.lookAt(cameraTarget);

        const distance = spherical.radius;
        updateVisibility(distance);
        animateSceneObjects(elapsed, distance);
        renderer.render(scene, camera);
    }

    function stepFlyAnimation() {
        if (!flyAnimation) {
            return;
        }

        flyAnimation.progress += 1;
        const progress = ease(Math.min(flyAnimation.progress / flyAnimation.duration, 1));
        cameraTarget.lerpVectors(flyAnimation.fromTarget, flyAnimation.toTarget, progress);
        cameraTargetGoal.copy(cameraTarget);

        const radius = flyAnimation.fromRadius + ((flyAnimation.toRadius - flyAnimation.fromRadius) * progress);
        spherical.radius = radius;
        sphericalGoal.radius = radius;

        if (flyAnimation.progress >= flyAnimation.duration) {
            flyAnimation = null;
        }
    }

    function updateVisibility(distance) {
        const rootAlpha = fadeIn(distance, 560, 1200);
        const childAlpha = Math.min(fadeRange(distance, 820, 1450), fadeIn(distance, 160, 330));
        const memoryAlpha = fadeRange(distance, 220, 540);

        rootGroups.forEach((item) => setVisibleAlpha(item.group, rootAlpha));
        childGroups.forEach((item) => setVisibleAlpha(item.group, childAlpha));
        memoryGroups.forEach((item) => setVisibleAlpha(item.group, memoryAlpha));
    }

    function animateSceneObjects(elapsed, distance) {
        rootGroups.forEach((item, index) => {
            item.mesh.rotation.y = elapsed * 0.22 + index;
            item.mesh.rotation.x = elapsed * 0.14 + (index * 0.4);
            item.wire.rotation.copy(item.mesh.rotation);
        });

        childGroups.forEach((item, index) => {
            if (!item.group.visible) {
                return;
            }

            item.mesh.rotation.y = elapsed * 0.36 + index;
            item.mesh.rotation.z = elapsed * 0.18 + (index * 0.3);
            item.wire.rotation.copy(item.mesh.rotation);
        });

        memoryGroups.forEach((item, index) => {
            if (!item.group.visible) {
                return;
            }

            item.mesh.scale.setScalar(1 + (Math.sin((elapsed * 1.4) + index) * 0.06));
            item.label.visible = distance < 170;
        });
    }

    function setVisibleAlpha(group, alpha) {
        group.visible = alpha > 0.01;

        if (!group.visible) {
            return;
        }

        group.traverse((object) => {
            if (!object.isMesh && !object.isSprite) {
                return;
            }

            const materials = Array.isArray(object.material) ? object.material : [object.material];
            materials.forEach((material) => {
                if (!material) {
                    return;
                }

                material.transparent = true;
                material.opacity = Math.min(material._baseOpacity ?? material.opacity, alpha * (material._baseOpacity ?? 1));
            });
        });
    }

    function saveBaseOpacity(group) {
        group.traverse((object) => {
            if (!object.isMesh && !object.isSprite) {
                return;
            }

            const materials = Array.isArray(object.material) ? object.material : [object.material];
            materials.forEach((material) => {
                if (material && material._baseOpacity === undefined) {
                    material._baseOpacity = material.opacity;
                }
            });
        });
    }

    function renderList() {
        els.list.replaceChildren();

        if (state.memories.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'memory-list__empty';
            empty.textContent = state.token ? '表示できる記憶はありません。' : 'API token 未設定';
            els.list.append(empty);
            return;
        }

        state.memories.slice(0, 80).forEach((memory) => {
            const category = categoryMap.get(String(memoryCategoryId(memory)));
            const item = document.createElement('button');
            item.className = 'memory-list__item';
            item.type = 'button';
            item.dataset.memoryId = String(resourceId(memory));

            if (state.activeMemoryId === String(resourceId(memory))) {
                item.classList.add('is-active');
            }

            const title = document.createElement('span');
            title.className = 'memory-list__title';
            title.textContent = memory.title || 'Untitled';

            const meta = document.createElement('span');
            meta.className = 'memory-list__meta';
            meta.textContent = [
                category?.name || '未分類',
                memory.period_key || null,
                memory.visibility === 'secret' ? 'secret' : null,
            ].filter(Boolean).join(' / ');

            item.append(title, meta);
            item.addEventListener('click', () => showMemoryDetail(memory, category));
            els.list.append(item);
        });
    }

    function renderMetrics() {
        const categories = flattenCategories(state.categories);
        els.metricCategoryCount.textContent = String(categories.length);
        els.metricMemoryCount.textContent = String(state.memories.length);
        els.metricSecretCount.textContent = String(state.secret?.locked_count || 0);
    }

    function showMemoryDetail(memory, category) {
        state.activeMemoryId = String(resourceId(memory));
        els.detail.hidden = false;
        els.detailCrumb.textContent = detailCrumb(category, memory);
        els.detailTitle.textContent = memory.title || 'Untitled';
        els.detailBody.textContent = memory.body || '';

        renderEmotionSection(memory);
        renderBeliefSection(memory);
        renderTagSection(memory);
        renderList();
    }

    function showCategoryDetail(category) {
        state.activeMemoryId = null;
        els.detail.hidden = false;
        els.detailCrumb.textContent = category.parentName || 'カテゴリー';
        els.detailTitle.textContent = category.name || 'カテゴリー';
        els.detailBody.textContent = [
            `表示記憶: ${category.memory_count || 0}`,
            `locked secret: ${category.locked_secret_count || 0}`,
        ].join('\n');

        els.detailEmotions.replaceChildren();
        els.detailBeliefs.replaceChildren();
        els.detailTags.replaceChildren();
        renderList();
    }

    function hideDetail() {
        state.activeMemoryId = null;
        els.detail.hidden = true;
        renderList();
    }

    function renderEmotionSection(memory) {
        els.detailEmotions.replaceChildren();
        const scores = memory.emotion_scores || {};

        if (Object.keys(scores).length === 0 && !memory.emotion_label) {
            return;
        }

        els.detailEmotions.append(sectionTitle('感情'));

        if (Object.keys(scores).length > 0) {
            Object.entries(scores)
                .sort((a, b) => Number(b[1]) - Number(a[1]))
                .forEach(([label, score]) => {
                    const row = document.createElement('div');
                    row.className = 'emotion-row';

                    const name = document.createElement('span');
                    name.textContent = label;

                    const track = document.createElement('div');
                    track.className = 'emotion-track';

                    const fill = document.createElement('div');
                    fill.className = 'emotion-fill';
                    fill.style.width = `${Math.max(0, Math.min(100, Number(score)))}%`;
                    fill.style.background = emotionColors[label] || '#60a5fa';
                    track.append(fill);

                    const value = document.createElement('span');
                    value.textContent = String(score);

                    row.append(name, track, value);
                    els.detailEmotions.append(row);
                });

            return;
        }

        const row = document.createElement('div');
        row.className = 'emotion-row';
        row.append(
            textNodeElement('span', memory.emotion_label || 'emotion'),
            textNodeElement('span', ''),
            textNodeElement('span', String(memory.emotion_intensity || '')),
        );
        els.detailEmotions.append(row);
    }

    function renderBeliefSection(memory) {
        els.detailBeliefs.replaceChildren();
        const beliefs = Array.isArray(memory.beliefs) ? memory.beliefs : [];
        const chains = Array.isArray(memory.chains) ? memory.chains : [];

        if (beliefs.length === 0 && chains.length === 0) {
            return;
        }

        els.detailBeliefs.append(sectionTitle('信念 / 鎖'));
        const row = document.createElement('div');
        row.className = 'chip-row';

        beliefs.forEach((belief) => row.append(chip(belief)));
        chains.forEach((chain) => row.append(chip(chain, 'chip--chain')));
        els.detailBeliefs.append(row);
    }

    function renderTagSection(memory) {
        els.detailTags.replaceChildren();
        const tags = Array.isArray(memory.tags) ? memory.tags : [];

        if (tags.length === 0) {
            return;
        }

        els.detailTags.append(sectionTitle('タグ'));
        const row = document.createElement('div');
        row.className = 'chip-row';
        tags.forEach((tag) => row.append(chip(tag)));
        els.detailTags.append(row);
    }

    function sectionTitle(text) {
        const element = document.createElement('div');
        element.className = 'section-title';
        element.textContent = text;

        return element;
    }

    function chip(text, className = '') {
        const element = document.createElement('span');
        element.className = `chip ${className}`.trim();
        element.textContent = text;

        return element;
    }

    function openUnlockDialog() {
        clearUnlockError();

        if (typeof els.unlockDialog.showModal === 'function') {
            els.unlockDialog.showModal();
        } else {
            els.unlockDialog.setAttribute('open', 'open');
        }

        els.unlockPassword.focus();
    }

    function closeUnlockDialog() {
        clearUnlockError();
        els.unlockPassword.value = '';

        if (typeof els.unlockDialog.close === 'function') {
            els.unlockDialog.close();
        } else {
            els.unlockDialog.removeAttribute('open');
        }
    }

    function clearUnlockError() {
        els.unlockError.textContent = '';
    }

    function setUnlockError(message) {
        els.unlockError.textContent = message;
    }

    function clearLoginStatus() {
        setLoginStatus('', '');
    }

    function setLoginStatus(message, type) {
        els.loginStatus.textContent = message;
        els.loginStatus.classList.toggle('is-error', type === 'error');
        els.loginStatus.classList.toggle('is-ok', type === 'ok');
    }

    function loginSuccessMessage(data) {
        const userName = data?.user?.name || data?.user?.email || 'user';
        const tenantName = data?.tenant?.name || 'tenant';

        return `${userName} / ${tenantName} でログインしました。`;
    }

    function handleAuthenticationRequired(message) {
        setPanelOpen('controls', true);
        setStatus('認証に失敗しました。ログインするか Bearer token を更新してください。', 'error');
        setLoginStatus(message || '401 Unauthorized', 'error');
        els.loginEmail.focus();
    }

    function setStatus(message, type) {
        els.status.textContent = message;
        els.status.classList.toggle('is-error', type === 'error');
        els.status.classList.toggle('is-ok', type === 'ok');
    }

    function setControlsDisabled(disabled) {
        [
            els.load,
            els.unlock,
            els.period,
            els.category,
            els.includeDescendants,
            els.apiBase,
            els.apiToken,
            els.loginEmail,
            els.loginPassword,
            els.loginSubmit,
        ].forEach((element) => {
            element.disabled = disabled;
        });
    }

    function setAuthControlsDisabled(disabled) {
        [els.loginEmail, els.loginPassword, els.loginSubmit, els.apiBase].forEach((element) => {
            element.disabled = disabled;
        });
    }

    function statusMessage() {
        const lockedCount = state.secret?.locked_count || 0;
        const visualSuffix = isWebglAvailable() ? '' : webglUnavailableMessage();

        if (state.unlockToken && state.secret?.locked === false) {
            return `同期済み。secret unlock は ${formatDateTime(state.unlockExpiresAt)} まで有効です。${visualSuffix}`;
        }

        if (lockedCount > 0) {
            return `同期済み。${lockedCount} 件の secret memory は locked state です。${visualSuffix}`;
        }

        return `同期済み。${visualSuffix}`;
    }

    function clearDynamicObjects() {
        clickable.splice(0, clickable.length);
        rootGroups.splice(0, rootGroups.length);
        childGroups.splice(0, childGroups.length);
        memoryGroups.splice(0, memoryGroups.length);

        if (!scene) {
            dynamicObjects.splice(0, dynamicObjects.length);
            return;
        }

        while (dynamicObjects.length > 0) {
            const object = dynamicObjects.pop();
            scene.remove(object);
            disposeObject(object);
        }
    }

    function disposeObject(object) {
        object.traverse((child) => {
            if (child.geometry) {
                child.geometry.dispose();
            }

            const materials = Array.isArray(child.material) ? child.material : [child.material].filter(Boolean);

            materials.forEach((material) => {
                if (material.map) {
                    material.map.dispose();
                }

                material.dispose?.();
            });
        });
    }

    function resetCameraForData() {
        if (!isWebglAvailable()) {
            return;
        }

        if (state.memories.length === 0 && flattenCategories(state.categories).length === 0) {
            return;
        }

        cameraTarget.set(0, 0, 0);
        cameraTargetGoal.copy(cameraTarget);
        sphericalGoal.radius = 3600;
    }

    function resize() {
        if (!isWebglAvailable()) {
            return;
        }

        const width = window.innerWidth;
        const height = window.innerHeight;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height, false);
    }

    function rootPosition(index, total) {
        if (total <= 1) {
            return new THREE.Vector3(0, 0, 0);
        }

        const angle = index * 2.3999632297;
        const ring = 540 + (Math.floor(index / 6) * 260);
        const height = ((index % 3) - 1) * 220;

        return new THREE.Vector3(
            Math.cos(angle) * ring,
            height,
            Math.sin(angle) * ring,
        );
    }

    function childRelativePosition(index, total) {
        const angle = (index / Math.max(total, 1)) * Math.PI * 2;
        const radius = 150 + (Math.floor(index / 8) * 60);

        return new THREE.Vector3(
            Math.cos(angle) * radius,
            Math.sin(angle * 1.3) * 72,
            Math.sin(angle) * radius,
        );
    }

    function memoryPosition(basePosition, index, total) {
        const angle = (index / Math.max(total, 1)) * Math.PI * 2;
        const radius = 72 + (Math.floor(index / 10) * 34);

        return new THREE.Vector3(
            basePosition.x + (Math.cos(angle) * radius),
            basePosition.y + (Math.sin(angle) * radius * 0.58),
            basePosition.z + (Math.sin(angle * 1.7) * radius * 0.42),
        );
    }

    function flattenCategories(categories, depth = 0, parentName = '') {
        return (categories || []).flatMap((category) => {
            const current = {
                ...category,
                depth,
                parentName,
            };

            return [
                current,
                ...flattenCategories(category.children || [], depth + 1, category.name),
            ];
        });
    }

    function syntheticRootsForUncategorizedMemories() {
        return state.memories.length > 0
            ? [{ public_id: 'uncategorized', id: 0, name: '未分類', children: [], memory_count: state.memories.length, locked_secret_count: 0 }]
            : [];
    }

    function dominantEmotion(memory) {
        const scores = memory.emotion_scores || {};
        const entries = Object.entries(scores).filter(([, score]) => Number.isFinite(Number(score)));

        if (entries.length > 0) {
            return entries.sort((a, b) => Number(b[1]) - Number(a[1]))[0][0];
        }

        return memory.emotion_label || '';
    }

    function strongestEmotionScore(memory) {
        const scores = Object.values(memory.emotion_scores || {}).map((score) => Number(score));
        const numericScores = scores.filter((score) => Number.isFinite(score));

        if (numericScores.length > 0) {
            return Math.max(...numericScores);
        }

        return Number(memory.emotion_intensity || 0) * 20;
    }

    function importanceScore(memory) {
        const raw = Number(memory.importance_score);

        if (Number.isFinite(raw)) {
            return Math.max(0, Math.min(1, raw));
        }

        const intensity = Number(memory.emotion_intensity || 0);

        return Math.max(0.35, Math.min(1, intensity / 5));
    }

    function detailCrumb(category, memory) {
        const parts = [
            category?.parentName || null,
            category?.name || null,
            memory.period_key || null,
        ].filter(Boolean);

        return parts.join(' / ');
    }

    function sphericalToCartesian(value) {
        return new THREE.Vector3(
            value.radius * Math.sin(value.phi) * Math.sin(value.theta),
            value.radius * Math.cos(value.phi),
            value.radius * Math.sin(value.phi) * Math.cos(value.theta),
        );
    }

    function fadeRange(value, low, high) {
        return Math.max(0, Math.min(1, (high - value) / (high - low)));
    }

    function fadeIn(value, low, high) {
        return Math.max(0, Math.min(1, (value - low) / (high - low)));
    }

    function ease(value) {
        return value < 0.5 ? 2 * value * value : -1 + ((4 - (2 * value)) * value);
    }

    function trimTrailingSlash(value) {
        return value.replace(/\/+$/, '');
    }

    function isWebglAvailable() {
        return Boolean(renderer && scene && camera && raycaster && pointer && clock);
    }

    function webglUnavailableMessage() {
        return 'WebGL を初期化できないため、一覧モードで表示します。';
    }

    function authorizationHeader(token) {
        return token.toLowerCase().startsWith('bearer ') ? token : `Bearer ${token}`;
    }

    function truncate(text, length) {
        return String(text).length > length ? `${String(text).slice(0, length - 1)}...` : String(text);
    }

    function textNodeElement(tagName, text) {
        const element = document.createElement(tagName);
        element.textContent = text;

        return element;
    }

    function formatDateTime(value) {
        if (!value) {
            return '期限未取得';
        }

        try {
            return new Intl.DateTimeFormat('ja-JP', {
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date(value));
        } catch {
            return value;
        }
    }
}
