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
    <style>
        #spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        #spinner.active {
            display: flex;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .locked {
            pointer-events: none;
            opacity: 0.6;
        }
        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
        }
        .toast {
            margin-bottom: 10px;
            padding: 12px 24px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
        }
        .toast-success {
            background-color: #10b981;
        }
        .toast-error {
            background-color: #ef4444;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .ai-preview {
            display: none;
            background-color: #f0f9ff;
            border: 2px dashed #3b82f6;
            border-radius: 6px;
            padding: 12px;
            margin-top: 12px;
        }
        .ai-preview.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="spinner">
        <div class="spinner"></div>
    </div>

    <div id="toast-container"></div>

    <div class="absolute top-4 right-4">
        <a href="logout.php" class="text-red-500 hover:text-red-700 font-medium">Выйти</a>
    </div>

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Управление лендингами</h1>

            <div class="mb-6">
                <label for="landing-select" class="block text-gray-700 text-sm font-bold mb-2">Выберите лендинг:</label>
                <select 
                    id="landing-select" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-lg"
                >
                    <option value="">-- Выберите лендинг --</option>
                </select>
            </div>

            <div id="fields-container" class="space-y-6">
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
            const toastClass = type === 'success' ? 'toast-success' : 'toast-error';
            const toast = $('<div class="toast ' + toastClass + '">' + message + '</div>');
            $('#toast-container').append(toast);
            
            setTimeout(function() {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
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
                    <div class="field-block bg-gray-50 p-4 rounded-lg border border-gray-200" data-field="${fieldName}">
                        <label class="block text-gray-700 text-sm font-bold mb-2">${escapeHtml(config.label)}</label>
                        <textarea 
                            id="${textareaId}"
                            rows="${config.rows}"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                            placeholder="${escapeHtml(config.placeholder)}"
                        >${escapeHtml(value)}</textarea>
                        
                        <div class="flex gap-2 mt-3">
                            <button 
                                class="save-btn bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-200"
                                data-field="${fieldName}"
                            >
                                Сохранить
                            </button>
                            <button 
                                class="ai-btn bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded transition duration-200"
                                data-field="${fieldName}"
                            >
                                🤖 Сгенерировать через ИИ
                            </button>
                        </div>
                        
                        <div id="${previewId}" class="ai-preview">
                            <div class="text-sm text-gray-600 mb-2 font-semibold">Предварительный просмотр:</div>
                            <div class="ai-preview-text bg-white p-3 rounded border border-gray-200 mb-3"></div>
                            <div class="flex gap-2">
                                <button 
                                    class="apply-btn bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded transition duration-200"
                                    data-field="${fieldName}"
                                >
                                    Применить
                                </button>
                                <button 
                                    class="cancel-btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition duration-200"
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
            const $fieldBlock = $('.field-block[data-field="' + fieldName + '"]');
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
