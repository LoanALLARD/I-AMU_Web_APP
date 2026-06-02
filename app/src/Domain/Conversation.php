<?php

namespace Domain;

class Conversation {
    // a faire : 
    // Doit recup tous les context des messages qui appartienne
    // a la conv.

    private int $id;
    private int $session_id;
    private string $name;

    public function __construct(int $user_id, int $session_id, string $name){
        $this->id = $id;
        $this->session_id = $session_id;
        $this->name = $name;
    }

    /*
    SELECT m.name,m.adapter from models m
    join interactions i
    ON m.id = i.model_id 
    join conversations c ON i.conversation_id = c.id
    where c.user_id = 16;
    */

    /*
    Select api_metadata from interactions i
    JOIN conversations c
    ON c.id = i.conversation_id
    JOIN users u
    ON u.id = c.user_id
    where u.id = 16;
     */
    // public function 

}