<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка - Управление лендингами</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #spinner { display: none; }
        #spinner.active { display: flex; }
        .locked { pointer-events: none; opacity: 0.7; }
        .btn-icon { display: inline-flex; align-items: center; gap: 0.5rem; }
        .ai-gradient { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); }
        .ai-gradient:hover { background: linear-gradient(135deg, #4f46e5 0%, #9333ea 100%); }
        .ai-preview { display: none; }
        .ai-preview.active { display: block; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

<div id="spinner" class="fixed inset-0 z-50 items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white p-6 rounded-2xl shadow-2xl flex flex-col items-center gap-4">
        <svg class="animate-spin h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-sm font-medium text-slate-600">Обработка...</p>
    </div>
</div>

<div id="toast-container" class="fixed top-6 right-6 z-50 flex flex-col gap-3"></div>

<div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        
        <header class="mb-8 text-center sm:text-left sm:flex sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Управление контентом</h1>
                <p class="mt-2 text-slate-500">Редактирование SEO-данных и заголовков лендингов</p>
            </div>
            <a href="logout.php" class="hidden sm:inline-flex mt-4 sm:mt-0 items-center text-sm font-medium text-slate-500 hover:text-red-600 transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Выход
            </a>
        </header>

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <div class="p-6 sm:p-8 border-b border-slate-100 bg-slate-50/50">
                <label for="landing-select" class="block text-sm font-semibold text-slate-700 mb-2">Выберите лендинг</label>
                <div class="relative">
                    <select 
                        id="landing-select" 
                        class="block w-full pl-4 pr-10 py-3 text-base border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-xl shadow-sm transition-shadow cursor-pointer"
                    >
                        <option value="">-- Выберите проект --</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </div>
                </div>
            </div>

            <div id="fields-container" class="p-6 sm:p-8 space-y-8">
                <div class="text-center py-12 text-slate-400">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <p class="mt-2 text-sm">Выберите лендинг из списка выше</p>
                </div>
            </div>
        </div>
        
        <div class="mt-6 text-center sm:hidden">
             <a href="logout.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-red-600">
                Выйти из системы
            </a>
        </div>
    </div>
</div>

    <script>
    $(document).ready(function() {
        /** @type {number | null} */
        let currentLandingId = null;
        
        /** @type {Object.<string, {label: string, rows: number, placeholder: string}>} */
        const fieldConfig = <?= json_encode($fieldConfig) ?>;

        /** @returns {void} */
        function showSpinner() {
            $('#spinner').addClass('active');
            $('body').addClass('locked');
        }

        /** @returns {void} */
        function hideSpinner() {
            $('#spinner').removeClass('active');
            $('body').removeClass('locked');
        }

        /**
         * @param {string} message 
         * @param {'success' | 'error'} type 
         * @returns {void}
         */
        function showToast(message, type = 'success') {
            const toastClass = type === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200';
            const icon = type === 'success' 
                ? '<svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
                : '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
            
            const toast = $(`
                <div class="toast flex items-center w-full max-w-xs p-4 rounded-xl shadow-lg border ${toastClass} transform transition-all duration-300">
                    <div class="flex-shrink-0">${icon}</div>
                    <div class="ml-3 text-sm font-medium">${message}</div>
                </div>
            `);
            $('#toast-container').append(toast);
            
            setTimeout(function() {
                toast.css('opacity', '0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        /** @returns {void} */
        function loadLandings() {
            showSpinner();
            $.ajax({
                url: 'handler.php',
                method: 'POST',
                data: { action: 'get_landings' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const $select = $('#landing-select');
                        response.data.forEach(function(landing) {
                            $select.append('<option value="' + landing.id + '">' + escapeHtml(landing.landing_name) + '</option>');
                        });
                    } else {
                        showToast('Ошибка загрузки списка лендингов', 'error');
                    }
                },
                error: function() {
                    showToast('Ошибка сервера', 'error');
                },
                complete: function() {
                    hideSpinner();
                }
            });
        }

        /**
         * @param {number} landingId 
         * @returns {void}
         */
        function loadLandingData(landingId) {
            currentLandingId = landingId;
            showSpinner();
            
            $.ajax({
                url: 'handler.php',
                method: 'POST',
                data: { action: 'get_landing_data', landing_id: landingId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderFields(response.data);
                    } else {
                        showToast('Ошибка загрузки данных лендинга', 'error');
                    }
                },
                error: function() {
                    showToast('Ошибка сервера', 'error');
                },
                complete: function() {
                    hideSpinner();
                }
            });
        }

        /**
         * @param {Object.<string, string>} data 
         * @returns {void}
         */
        function renderFields(data) {
            const $container = $('#fields-container');
            $container.empty();

            $.each(fieldConfig, function(fieldName, config) {
                const value = data[fieldName] || '';
                const previewId = 'preview-' + fieldName;
                const textareaId = 'field-' + fieldName;
                
                const fieldHtml = `
                    <div class="field-group bg-white rounded-xl border border-slate-200 shadow-sm p-6 transition-shadow hover:shadow-md" data-field="${fieldName}">
                        <div class="flex justify-between items-center mb-4">
                            <label for="${textareaId}" class="block text-base font-semibold text-slate-800">
                                ${escapeHtml(config.label)}
                                <span class="block text-xs font-normal text-slate-400 mt-1">Поле: ${escapeHtml(fieldName)}</span>
                            </label>
                        </div>
                        
                        <textarea 
                            id="${textareaId}"
                            rows="${config.rows}"
                            class="w-full p-4 text-slate-700 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all resize-y font-mono text-sm leading-relaxed"
                            placeholder="${escapeHtml(config.placeholder)}"
                        >${escapeHtml(value)}</textarea>
                        
                        <div class="mt-5 flex flex-wrap gap-3 items-center">
                            <button 
                                class="save-btn btn-icon px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm hover:shadow transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                data-field="${fieldName}"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                Сохранить
                            </button>
                            <button 
                                class="ai-btn btn-icon px-5 py-2.5 ai-gradient text-white text-sm font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed group"
                                data-field="${fieldName}"
                            >
                                <svg class="w-4 h-4 group-hover:rotate-12 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                Генерировать ИИ
                            </button>
                        </div>
                        
                        <div id="${previewId}" class="ai-preview mt-6 p-5 bg-indigo-50 border border-indigo-100 rounded-xl relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-indigo-400"></div>
                            <p class="text-xs font-bold text-indigo-500 uppercase tracking-wider mb-3">Предварительный просмотр ИИ</p>
                            <div class="ai-preview-text text-slate-700 text-sm whitespace-pre-wrap mb-4 font-sans"></div>
                            <div class="flex gap-3">
                                <button 
                                    class="apply-btn px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors"
                                    data-field="${fieldName}"
                                >
                                    Применить
                                </button>
                                <button 
                                    class="cancel-btn px-4 py-2 bg-white hover:bg-slate-50 text-slate-600 border border-slate-200 text-xs font-semibold rounded-lg shadow-sm transition-colors"
                                    data-field="${fieldName}"
                                >
                                    Отменить
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                $container.append(fieldHtml);
            });
        }

        /**
         * @param {string} text 
         * @returns {string}
         */
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>\"']/g, function(m) { return map[m]; });
        }

        /**
         * @param {string} fieldName 
         * @param {string} value 
         * @returns {void}
         */
        function saveField(fieldName, value) {
            showSpinner();
            
            $.ajax({
                url: 'handler.php',
                method: 'POST',
                data: {
                    action: 'save_field',
                    landing_id: currentLandingId,
                    field_name: fieldName,
                    value: value
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast('Поле "' + fieldConfig[fieldName].label + '" сохранено', 'success');
                    } else {
                        showToast(response.error || 'Ошибка сохранения', 'error');
                    }
                },
                error: function() {
                    showToast('Ошибка сервера', 'error');
                },
                complete: function() {
                    hideSpinner();
                }
            });
        }

        /**
         * @param {string} fieldName 
         * @returns {void}
         */
        function generateAI(fieldName) {
            const $fieldBlock = $('.field-group[data-field="' + fieldName + '"]');
            const $preview = $('#preview-' + fieldName);
            const $textarea = $('#field-' + fieldName);
            
            showSpinner();
            $textarea.prop('disabled', true);
            
            $.ajax({
                url: 'handler.php',
                method: 'POST',
                data: {
                    action: 'generate_ai',
                    landing_id: currentLandingId,
                    field_name: fieldName,
                    current_value: $textarea.val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $preview.find('.ai-preview-text').text(response.generated_text);
                        $preview.data('generated-text', response.generated_text);
                        $preview.addClass('active');
                    } else {
                        showToast(response.error || 'Ошибка генерации', 'error');
                    }
                },
                error: function() {
                    showToast('Ошибка сервера', 'error');
                },
                complete: function() {
                    hideSpinner();
                    $textarea.prop('disabled', false);
                }
            });
        }

        $('#landing-select').on('change', function() {
            const landingId = $(this).val();
            if (landingId) {
                loadLandingData(Number(landingId));
            } else {
                $('#fields-container').empty();
                currentLandingId = null;
            }
        });

        $(document).on('click', '.save-btn', function() {
            const fieldName = $(this).data('field');
            const value = $('#field-' + fieldName).val();
            saveField(fieldName, value);
        });

        $(document).on('click', '.ai-btn', function() {
            const fieldName = $(this).data('field');
            generateAI(fieldName);
        });

        $(document).on('click', '.apply-btn', function() {
            const fieldName = $(this).data('field');
            const $preview = $('#preview-' + fieldName);
            const generatedText = $preview.data('generated-text');
            
            $('#field-' + fieldName).val(generatedText);
            $preview.removeClass('active');
            
            showToast('Текст применён', 'success');
        });

        $(document).on('click', '.cancel-btn', function() {
            const fieldName = $(this).data('field');
            $('#preview-' + fieldName).removeClass('active');
        });

        loadLandings();
    });
    </script>
</body>
</html>
