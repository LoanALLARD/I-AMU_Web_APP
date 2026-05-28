CREATE TABLE PLACE(
   PlaceId INTEGER,
   Name VARCHAR(255) ,
   Address VARCHAR(100) ,
   City VARCHAR(50) ,
   ZipCode VARCHAR(50) ,
   PRIMARY KEY(PlaceId)
);

CREATE TABLE DEPARTMENT(
   DepartmentId INTEGER,
   Name VARCHAR(50) ,
   Description TEXT,
   PlaceId INTEGER NOT NULL,
   PRIMARY KEY(DepartmentId),
   FOREIGN KEY(PlaceId) REFERENCES PLACE(PlaceId)
);

CREATE TABLE USER_(
   UserId INTEGER,
   Email VARCHAR(255)  NOT NULL,
   PasswordHash VARCHAR(255)  NOT NULL,
   FirstName VARCHAR(50) ,
   LastName VARCHAR(255) ,
   CreatedAt TIMESTAMP,
   LastLogin DATE,
   IsActive BOOLEAN,
   ConsentDate TIMESTAMP,
   ConsentVersion VARCHAR(50) ,
   Theme VARCHAR(50) ,
   ArchiveDuration SMALLINT,
   PRIMARY KEY(UserId),
   UNIQUE(Email)
);

CREATE TABLE EMAIL_DOMAIN_CONFIG_(
   DomainId SMALLINT,
   Domain VARCHAR(50) ,
   Role VARCHAR(50) ,
   IsActive BOOLEAN,
   PRIMARY KEY(DomainId)
);

CREATE TABLE TEACHER(
   UserId INTEGER,
   IsSpecialised BOOLEAN,
   Title VARCHAR(50) ,
   PRIMARY KEY(UserId),
   FOREIGN KEY(UserId) REFERENCES USER_(UserId)
);

CREATE TABLE STUDENT(
   UserId INTEGER,
   StudentNumber VARCHAR(50) ,
   PRIMARY KEY(UserId),
   FOREIGN KEY(UserId) REFERENCES USER_(UserId)
);

CREATE TABLE RESOURCE(
   ResourceId INTEGER,
   Code VARCHAR(50) ,
   Name VARCHAR(50) ,
   Description TEXT,
   Semester VARCHAR(50) ,
   State VARCHAR(50) ,
   UserId INTEGER NOT NULL,
   DepartmentId INTEGER NOT NULL,
   PRIMARY KEY(ResourceId),
   FOREIGN KEY(UserId) REFERENCES TEACHER(UserId),
   FOREIGN KEY(DepartmentId) REFERENCES DEPARTMENT(DepartmentId)
);

CREATE TABLE MODEL(
   ModeleId INTEGER,
   Name VARCHAR(255) ,
   Version VARCHAR(255) ,
   Provider VARCHAR(255) ,
   MaxTokens INTEGER,
   ContextWindow INTEGER,
   IsActive BOOLEAN,
   CreatedAt TIMESTAMP,
   ResourceId INTEGER,
   PRIMARY KEY(ModeleId),
   FOREIGN KEY(ResourceId) REFERENCES RESOURCE(ResourceId)
);

CREATE TABLE SESSION(
   SessionId INTEGER,
   Name VARCHAR(255) ,
   StartsAt TIMESTAMP,
   EndsAt TIMESTAMP,
   ClosedAt TIMESTAMP,
   AccessCode VARCHAR(255) ,
   SystemPromptOverride TEXT,
   MaxInputSize INTEGER,
   Instructions TEXT,
   Type VARCHAR(50) ,
   ResourceId INTEGER NOT NULL,
   PRIMARY KEY(SessionId),
   FOREIGN KEY(ResourceId) REFERENCES RESOURCE(ResourceId)
);

CREATE TABLE ADMINISTRATOR(
   UserId INTEGER,
   IsSuperAdmin BOOLEAN,
   PRIMARY KEY(UserId),
   FOREIGN KEY(UserId) REFERENCES USER_(UserId)
);

CREATE TABLE CONVERSATION(
   ConversationId INTEGER,
   Name VARCHAR(255) ,
   CreatedAt DATE NOT NULL,
   IsArchived BOOLEAN,
   UserId INTEGER,
   SessionId INTEGER,
   PRIMARY KEY(ConversationId),
   FOREIGN KEY(UserId) REFERENCES USER_(UserId),
   FOREIGN KEY(SessionId) REFERENCES SESSION(SessionId)
);

CREATE TABLE INTERACTION(
   PromptId INTEGER,
   Prompt TEXT NOT NULL,
   Response TEXT,
   SentAt DATE NOT NULL,
   Latency SMALLINT,
   InputTokens INTEGER,
   OutputTokens INTEGER,
   UserFeedBack SMALLINT,
   ModeleId INTEGER NOT NULL,
   ConversationId INTEGER NOT NULL,
   PRIMARY KEY(PromptId),
   FOREIGN KEY(ModeleId) REFERENCES MODEL(ModeleId),
   FOREIGN KEY(ConversationId) REFERENCES CONVERSATION(ConversationId)
);

CREATE TABLE RESEARCHER(
   UserId_1 INTEGER,
   Laboratory VARCHAR(255) ,
   AuthorizedAt TIMESTAMP,
   UserId INTEGER NOT NULL,
   PRIMARY KEY(UserId_1),
   FOREIGN KEY(UserId_1) REFERENCES USER_(UserId),
   FOREIGN KEY(UserId) REFERENCES ADMINISTRATOR(UserId)
);

CREATE TABLE TeachesIn(
   UserId INTEGER,
   ResourceId INTEGER,
   PRIMARY KEY(UserId, ResourceId),
   FOREIGN KEY(UserId) REFERENCES TEACHER(UserId),
   FOREIGN KEY(ResourceId) REFERENCES RESOURCE(ResourceId)
);

CREATE TABLE Accesses(
   UserId INTEGER,
   ResourceId INTEGER,
   PRIMARY KEY(UserId, ResourceId),
   FOREIGN KEY(UserId) REFERENCES STUDENT(UserId),
   FOREIGN KEY(ResourceId) REFERENCES RESOURCE(ResourceId)
);

CREATE TABLE Authorizes(
   ModeleId INTEGER,
   SessionId INTEGER,
   PRIMARY KEY(ModeleId, SessionId),
   FOREIGN KEY(ModeleId) REFERENCES MODEL(ModeleId),
   FOREIGN KEY(SessionId) REFERENCES SESSION(SessionId)
);

CREATE TABLE IsAffiliatedWith(
   DepartmentId INTEGER,
   UserId INTEGER,
   PRIMARY KEY(DepartmentId, UserId),
   FOREIGN KEY(DepartmentId) REFERENCES DEPARTMENT(DepartmentId),
   FOREIGN KEY(UserId) REFERENCES RESEARCHER(UserId_1)
);

CREATE TABLE Administers(
   DepartmentId INTEGER,
   UserId INTEGER,
   PRIMARY KEY(DepartmentId, UserId),
   FOREIGN KEY(DepartmentId) REFERENCES DEPARTMENT(DepartmentId),
   FOREIGN KEY(UserId) REFERENCES ADMINISTRATOR(UserId)
);

CREATE TABLE Enrollment(
   UserId INTEGER,
   SessionId INTEGER,
   JoinAt TIMESTAMP,
   IsActive BOOLEAN,
   PRIMARY KEY(UserId, SessionId),
   FOREIGN KEY(UserId) REFERENCES STUDENT(UserId),
   FOREIGN KEY(SessionId) REFERENCES SESSION(SessionId)
);
