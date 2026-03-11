<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\Call;
use App\Models\Lead;
use Exception;

class CallController
{
    /**
     * POST /calls/upload
     * Recebe um arquivo de áudio via FormData para gravação.
     */
    public function upload(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('user_id');

        try {
            if (!isset($_FILES['audio'])) {
                throw new Exception("Nenhum arquivo de áudio enviado.");
            }

            $leadId = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
            if (!$leadId || !Lead::findById($leadId)) {
                throw new Exception("Lead inválido ou não informado.");
            }

            $fileArray = $_FILES['audio'];

            // Validar tamanho e erro
            if ($fileArray['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Erro no envio do arquivo. Código: " . $fileArray['error']);
            }

            if ($fileArray['size'] > 25 * 1024 * 1024) { // OpenAI Whisper Limit
                throw new Exception("O arquivo excede o limite de 25MB suportado pela OpenAI.");
            }

            // Sanitizar nome e configurar caminho de destino seguro
            $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
            if (!$ext) $ext = 'weba'; // Default for mic captures

            $safeFileName = time() . '_' . uniqid() . '.' . $ext;
            
            $storageDir = ROOT_PATH . "/storage/calls/{$tenantId}/{$leadId}";
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $destination = "{$storageDir}/{$safeFileName}";

            if (!move_uploaded_file($fileArray['tmp_name'], $destination)) {
                throw new Exception("Falha ao salvar o arquivo no disco do servidor.");
            }

            // Criar o registro da Call no estado inicial
            $callId = Call::create([
                'tenant_id' => $tenantId,
                'lead_id'   => $leadId,
                'user_id'   => $userId,
                'title'     => 'Gravação Comercial - ' . date('d/m/Y H:i'),
                'audio_path'=> $destination,
                'status'    => Call::STATUS_STORED
            ]);

            // Despachar a análise em background para não travar o Upload!
            // Num sistema ideal usaríamos Symfony Messenger/Redis.
            // Aqui, por não haver Queue Manager garantido no container, invocaremos de forma simples via cli background async.
            $scriptPath = ROOT_PATH . '/app/Jobs/ProcessCallJob.php';
            $cmd = "php {$scriptPath} {$callId} {$tenantId} > /dev/null 2>&1 &";
            
            if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B php {$scriptPath} {$callId} {$tenantId}";
            }
            
            pclose(popen($cmd, "r")); // Executa fire-and-forget

            echo json_encode([
                'success' => true,
                'call_id' => $callId,
                'message' => 'Áudio enviado. Transcrição e análise iniciadas em background.'
            ]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /calls/status?ids=1,2,3
     * Retorna o status de múltiplas ligações para polling do frontend.
     */
    public function status(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');
        
        $tenantId = Session::get('tenant_id');
        $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

        $results = [];
        foreach ($ids as $id) {
            $call = Call::findById((int)$id, $tenantId);
            if ($call) {
                // Return safe public fields
                $call['analysis_data_decoded'] = $call['analysis_data'] ? json_decode($call['analysis_data'], true) : null;
                $results[(int)$id] = [
                    'status' => $call['status'],
                    'duration' => $call['duration'],
                    'transcript_clean' => $call['transcript_clean'],
                    'analysis_data' => $call['analysis_data_decoded'],
                    'error_message' => $call['error_message']
                ];
            }
        }

        echo json_encode(['success' => true, 'calls' => $results]);
    }
}
