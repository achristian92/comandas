<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitor de Impresión - Comandas</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-usb { background: #dbeafe; color: #1e40af; }
        .badge-red { background: #fef3c7; color: #92400e; }
        .log-entry { border-left: 3px solid #e5e7eb; padding-left: 12px; margin-bottom: 12px; animation: slideIn 0.3s ease-out; }
        .log-entry.success { border-left-color: #10b981; }
        .log-entry.error { border-left-color: #ef4444; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
    </style>
</head>
<body style="background: linear-gradient(135deg, #33b394 0%, #2e4050 100%); min-height: 100vh;">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">🖨️ Monitor de Impresión</h1>
                    <p class="text-gray-600 mt-1">Sistema de comandas en tiempo real</p>
                    <div class="mt-2 inline-flex items-center gap-2 bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-1 rounded-lg text-sm font-medium">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        ⚠️ NO CERRAR ESTA PÁGINA - Sistema activo
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <div class="status-dot bg-green-500" id="connectionStatus"></div>
                        <span class="text-sm font-medium text-gray-700" id="connectionText">Conectando...</span>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-500">Instancia</div>
                        <div class="text-sm font-mono font-semibold text-gray-700" id="instanceDisplay">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Comandas</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2" id="totalCommands">0</p>
                    </div>
                    <div class="bg-indigo-100 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Exitosas</p>
                        <p class="text-3xl font-bold text-green-600 mt-2" id="successCommands">0</p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Fallidas</p>
                        <p class="text-3xl font-bold text-red-600 mt-2" id="failedCommands">0</p>
                    </div>
                    <div class="bg-red-100 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Tasa Éxito</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2" id="successRate">100%</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Commands -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📋 Comandas Recientes</h2>
                <div id="recentCommands" class="space-y-3 max-h-96 overflow-y-auto">
                    <div class="text-center text-gray-400 py-8">
                        <svg class="w-16 h-16 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p>Esperando comandas...</p>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📝 Log de Actividad</h2>
                <div id="activityLog" class="space-y-2 max-h-96 overflow-y-auto">
                    <div class="text-center text-gray-400 py-8">
                        <svg class="w-16 h-16 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p>Sin actividad reciente</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ mix('js/app.js') }}"></script>
    <script>
        const stats = { total: 0, success: 0, failed: 0 };
        const companyUuid = '{{ config('okfac.company_uuid') }}';
        const channelName = `${companyUuid}.commands`;
        const instanceId = Math.random().toString(36).substring(7);
        
        // Funciones de localStorage
        function getTodayDate() {
            const today = new Date();
            return today.toISOString().split('T')[0]; // YYYY-MM-DD
        }
        
        function cleanOldCommands() {
            const today = getTodayDate();
            const stored = localStorage.getItem('comandas');
            if (!stored) return;
            
            try {
                const allCommands = JSON.parse(stored);
                const todayCommands = allCommands.filter(cmd => cmd.date === today);
                localStorage.setItem('comandas', JSON.stringify(todayCommands));
                console.log(`🧹 Limpieza: ${allCommands.length - todayCommands.length} comandas antiguas eliminadas`);
            } catch (e) {
                console.error('Error limpiando comandas:', e);
                localStorage.removeItem('comandas');
            }
        }
        
        function saveCommand(data, success, type = 'Command') {
            const today = getTodayDate();
            const command = {
                date: today,
                timestamp: new Date().toISOString(),
                success: success,
                type: type,
                data: data
            };
            
            try {
                const stored = localStorage.getItem('comandas');
                const commands = stored ? JSON.parse(stored) : [];
                commands.unshift(command); // Agregar al inicio
                
                // Mantener máximo 100 comandas
                if (commands.length > 100) {
                    commands.splice(100);
                }
                
                localStorage.setItem('comandas', JSON.stringify(commands));
            } catch (e) {
                console.error('Error guardando comanda:', e);
            }
        }
        
        function loadTodayCommands() {
            const today = getTodayDate();
            const stored = localStorage.getItem('comandas');
            if (!stored) return;
            
            try {
                const allCommands = JSON.parse(stored);
                // Filtrar solo comandas del día actual tipo 'Comanda' (no precuentas 'Pre-Cuenta')
                const todayCommands = allCommands.filter(cmd => 
                    cmd.date === today && cmd.type === 'Comanda'
                );
                
                // Cargar comandas del día en orden inverso (más recientes primero)
                todayCommands.forEach(cmd => {
                    addRecentCommand(cmd.data, cmd.success, false); // false = no guardar de nuevo
                    if (cmd.success) {
                        stats.success++;
                    } else {
                        stats.failed++;
                    }
                    stats.total++;
                });
                
                updateStats();
                addLog(`📦 ${todayCommands.length} comandas del día cargadas (solo tipo COMANDA)`, 'info');
            } catch (e) {
                console.error('Error cargando comandas:', e);
            }
        }
        
        // Limpiar comandas antiguas al iniciar
        cleanOldCommands();
        
        document.getElementById('instanceDisplay').textContent = instanceId;
        addLog('Sistema iniciado', 'info');
        
        // Cargar comandas del día
        loadTodayCommands();

        // Listener genérico para ver todos los eventos
        Echo.connector.pusher.bind_global((eventName, data) => {
            console.log('🔔 EVENTO DETECTADO:', eventName, data);
        });

        window.Echo.channel(channelName)
            .listen('NewCommand', async (eventData) => {
                const eventId = Math.random().toString(36).substring(7);
                const timestamp = new Date().toLocaleTimeString();
                
                const detail = eventData.details?.[0] || {};
                
                addLog(`Comanda recibida - ${detail.table?.t_name || 'N/A'} - Orden #${detail.order?.num || 'N/A'}`, 'info');
                stats.total++;
                updateStats();

                try {
                    const resp = await fetch('http://comandas.test/print', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Frontend-Instance': instanceId,
                            'X-Event-Id': eventId
                        },
                        body: JSON.stringify({ data: eventData })
                    });
                    
                    const body = await resp.json();
                    
                    if (body.status === 'ok') {
                        stats.success++;
                        addLog(`✅ Impresión exitosa - ${detail.table?.t_name}`, 'success');
                        addRecentCommand(detail, true, true);
                        saveCommand(detail, true, eventData.type || 'Command');
                    } else {
                        stats.failed++;
                        addLog(`❌ Error: ${body.message}`, 'error');
                        addRecentCommand(detail, false, true);
                        saveCommand(detail, false, eventData.type || 'Command');
                    }
                } catch (err) {
                    stats.failed++;
                    addLog(`❌ Error de conexión: ${err.message}`, 'error');
                    addRecentCommand(detail, false, true);
                    saveCommand(detail, false, eventData.type || 'Command');
                }
                
                updateStats();
            })
            .listen('NewPreAccount', async (eventData) => {
                const eventId = Math.random().toString(36).substring(7);
                const timestamp = new Date().toLocaleTimeString();
                
                const detail = eventData.details || {};
                
                addLog(`Precuenta recibida - ${detail.table?.t_name || 'N/A'} - Orden #${detail.order?.num || 'N/A'}`, 'info');
                stats.total++;
                updateStats();

                try {
                    const resp = await fetch('http://comandas.test/print', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Frontend-Instance': instanceId,
                            'X-Event-Id': eventId
                        },
                        body: JSON.stringify({ data: eventData })
                    });
                    
                    const body = await resp.json();
                    
                    if (body.status === 'ok') {
                        stats.success++;
                        addLog(`✅ Precuenta impresa exitosamente - ${detail.table?.t_name}`, 'success');
                        // No mostrar precuentas en el dashboard
                        saveCommand(detail, true, eventData.type || 'Pre-Cuenta');
                    } else {
                        stats.failed++;
                        addLog(`❌ Error en precuenta: ${body.message}`, 'error');
                        // No mostrar precuentas en el dashboard
                        saveCommand(detail, false, eventData.type || 'Pre-Cuenta');
                    }
                } catch (err) {
                    stats.failed++;
                    addLog(`❌ Error de conexión en precuenta: ${err.message}`, 'error');
                    // No mostrar precuentas en el dashboard
                    saveCommand(detail, false, eventData.type || 'Pre-Cuenta');
                }
                
                updateStats();
            });

        Echo.connector.pusher.connection.bind('connected', () => {
            document.getElementById('connectionStatus').className = 'status-dot bg-green-500';
            document.getElementById('connectionText').textContent = 'Conectado';
            addLog('Conectado al servidor WebSocket', 'success');
        });

        Echo.connector.pusher.connection.bind('disconnected', () => {
            document.getElementById('connectionStatus').className = 'status-dot bg-red-500';
            document.getElementById('connectionText').textContent = 'Desconectado';
            addLog('Desconectado del servidor', 'error');
        });

        function updateStats() {
            document.getElementById('totalCommands').textContent = stats.total;
            document.getElementById('successCommands').textContent = stats.success;
            document.getElementById('failedCommands').textContent = stats.failed;
            const rate = stats.total > 0 ? Math.round((stats.success / stats.total) * 100) : 100;
            document.getElementById('successRate').textContent = rate + '%';
        }

        function addLog(message, type) {
            const log = document.getElementById('activityLog');
            if (log.querySelector('.text-center')) log.innerHTML = '';
            
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.innerHTML = `
                <div class="flex items-start gap-2">
                    <span class="text-xs text-gray-500 whitespace-nowrap">${new Date().toLocaleTimeString()}</span>
                    <span class="text-sm text-gray-700">${message}</span>
                </div>
            `;
            log.insertBefore(entry, log.firstChild);
            
            if (log.children.length > 50) log.removeChild(log.lastChild);
        }

        function addRecentCommand(data, success, shouldSave = true) {
            const container = document.getElementById('recentCommands');
            if (container.querySelector('.text-center')) container.innerHTML = '';
            
            const card = document.createElement('div');
            card.className = 'border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow';
            
            const printerInfo = data.printer?.pr_type === 'USB' 
                ? data.printer?.pr_name 
                : data.printer?.pr_ip;
            
            card.innerHTML = `
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="font-bold text-gray-800 text-lg">${data.table?.t_name || 'N/A'}</span>
                        <span class="text-gray-500 text-sm ml-2">Orden #${data.order?.num || 'N/A'}</span>
                    </div>
                    <span class="badge ${success ? 'badge-success' : 'badge-error'}">${success ? 'Exitosa' : 'Fallida'}</span>
                </div>
                <div class="text-sm text-gray-600 space-y-1 mb-3">
                    <div class="flex items-center gap-2">
                        <span>🏢</span>
                        <span>${data.table?.t_salon || 'N/A'}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span>👤</span>
                        <span>${data.client?.c_name || 'N/A'}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span>👔</span>
                        <span>Mozo: ${data.waiter?.u_name || 'N/A'} ${data.waiter?.u_last_name || ''}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span>🕐</span>
                        <span>${data.order?.issue_date || ''} ${data.order?.time || ''}</span>
                    </div>
                </div>
                <div class="border-t pt-2 mb-2">
                    <div class="text-xs font-semibold text-gray-700 mb-1">Items (${data.items?.length || 0}):</div>
                    <div class="text-xs text-gray-600 space-y-0.5">
                        ${(data.items || []).slice(0, 3).map(item => 
                            `<div>• ${item.i_quantity}x ${item.i_name}</div>`
                        ).join('')}
                        ${data.items?.length > 3 ? `<div class="text-gray-400">... y ${data.items.length - 3} más</div>` : ''}
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-2 border-t">
                    <span class="badge ${data.printer?.pr_type === 'USB' ? 'badge-usb' : 'badge-red'}">
                        ${data.printer?.pr_type || 'RED'}
                    </span>
                    <span class="text-xs text-gray-500">${printerInfo || 'N/A'}</span>
                </div>
            `;
            container.insertBefore(card, container.firstChild);
            
            if (container.children.length > 10) container.removeChild(container.lastChild);
        }
    </script>
</body>
</html>
