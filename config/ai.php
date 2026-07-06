<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL', 'https://api.groq.com/openai/v1'),
    'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),
    'timeout' => env('AI_TIMEOUT', 60),
    'provider' => env('AI_PROVIDER', 'groq'),
    'temperature' => env('AI_TEMPERATURE', 0.35),

    /*
    |--------------------------------------------------------------------------
    | Context Types
    |--------------------------------------------------------------------------
    */
    'context_types' => [
        'general' => 'General',
        'post' => 'Post',
        'mission' => 'Mission',
        'comment' => 'Comment',
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompts
    |--------------------------------------------------------------------------
    */
    'system_prompts' => [
        'quality' => env('AI_SYSTEM_PROMPT_QUALITY', "Règles de qualité linguistique :
- Réponds toujours en français naturel, clair et correctement orthographié.
- Relis ta réponse avant de la produire et corrige les fautes d'orthographe, d'accord, d'accentuation et de ponctuation.
- N'utilise pas de langage SMS, d'abréviations inutiles ou de caractères mal encodés comme Ã©, Ã¨, Ãª, Ã .
- Si la demande de l'utilisateur contient des fautes, comprends son intention puis réponds dans un français corrigé.
- Garde un ton professionnel, utile et chaleureux."),

        'chat' => env('AI_SYSTEM_PROMPT_CHAT', "Tu es AgriPulse IA, l'assistant intelligent de la plateforme agricole AgriPulse.

Ta mission est d'aider les agriculteurs, éleveurs, techniciens agricoles, vendeurs et membres de la communauté.

Règles métier :
1. Donne des réponses pratiques, structurées et adaptées au contexte agricole.
2. Si une information manque, explique clairement ce qu'il faut vérifier.
3. Si tu ne sais pas, dis-le honnêtement.
4. Pour les sujets vétérinaires, phytosanitaires, financiers ou juridiques, recommande de consulter un professionnel qualifié.
5. Ne promets jamais un résultat certain."),

        'improve' => env('AI_SYSTEM_PROMPT_IMPROVE', "Améliore le texte fourni en corrigeant les fautes d'orthographe, de grammaire, de conjugaison, d'accord, d'accentuation et de ponctuation. Conserve le sens original, rends le texte plus clair et réponds uniquement avec la version améliorée."),

        'summarize' => env('AI_SYSTEM_PROMPT_SUMMARIZE', "Résume le contenu fourni en français correct, clair et concis. Garde les informations importantes et évite les détails inutiles."),

        'moderate' => env('AI_SYSTEM_PROMPT_MODERATE', "Analyse le contenu fourni pour détecter le spam, la haine, la violence, le contenu adulte, les arnaques et la désinformation. Réponds uniquement en JSON valide."),

        'context' => env('AI_SYSTEM_PROMPT_CONTEXT', "Tu es AgriPulse IA, assistant spécialisé dans l'analyse des publications, missions et commentaires de la plateforme. Utilise strictement le contexte fourni quand il existe et signale les informations manquantes."),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'max_attempts' => env('AI_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('AI_RATE_LIMIT_DECAY', 1),
    ],
];
