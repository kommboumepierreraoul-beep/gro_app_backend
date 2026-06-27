<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW moderation_stats AS
            SELECT 
                'post' as content_type,
                status,
                COUNT(*) as total,
                AVG(toxicity_score) as avg_toxicity,
                AVG(spam_score) as avg_spam,
                AVG(hate_score) as avg_hate,
                AVG(violence_score) as avg_violence
            FROM moderation_posts
            GROUP BY status
            
            UNION ALL
            
            SELECT 
                'comment' as content_type,
                status,
                COUNT(*) as total,
                AVG(toxicity_score) as avg_toxicity,
                AVG(spam_score) as avg_spam,
                AVG(hate_score) as avg_hate,
                AVG(violence_score) as avg_violence
            FROM moderation_comments
            GROUP BY status
            
            UNION ALL
            
            SELECT 
                'message' as content_type,
                status,
                COUNT(*) as total,
                AVG(toxicity_score) as avg_toxicity,
                AVG(spam_score) as avg_spam,
                AVG(hate_score) as avg_hate,
                AVG(violence_score) as avg_violence
            FROM moderation_messages
            GROUP BY status
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS moderation_stats');
    }
};
