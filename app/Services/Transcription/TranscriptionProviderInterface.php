<?php

namespace App\Services\Transcription;

interface TranscriptionProviderInterface
{
    /**
     * Transcreve um arquivo de áudio.
     * 
     * @param string $audioPath Caminho absoluto para o arquivo de áudio
     * @return array [
     *      'text' => 'texto transcrito limpo', 
     *      'language' => 'pt', 
     *      'duration' => 120 (segundos),
     *      'raw_response' => array() (resposta bruta da API se aplicável)
     * ]
     * @throws \Exception em caso de falha na transcrição
     */
    public function transcribe(string $audioPath): array;
}
