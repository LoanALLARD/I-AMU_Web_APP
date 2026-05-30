create table models (
    name varchar(20) primary key,
    contextWindows int,
    modelSize varchar(4),
    compagny varchar(25),
    url varchar(500),
    adaptater varchar(25)
);

insert into models values('llama3.2:1b',128000,'1b','Meta' ,'http://localhost:8082/api/generate', 'ollama');