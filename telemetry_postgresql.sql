--
-- PostgreSQL database dump
--

-- Dumped from database version 9.6.3
-- Dumped by pg_dump version 9.6.5

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: speedtest_users; Type: TABLE; Schema: public; Owner: speedtest
--

CREATE TABLE speedtest_users (
    id integer NOT NULL,
    "timestamp" timestamp without time zone DEFAULT now() NOT NULL,
    ip text NOT NULL,
    ua text NOT NULL,
    lang text NOT NULL,
    dl text,
    ul text,
    ping text,
    jitter text,
    log text
);

-- Commented out the following line because it assumes the user of the speedtest server, @bplower
-- ALTER TABLE speedtest_users OWNER TO speedtest;

--
-- Name: speedtest_users_id_seq; Type: SEQUENCE; Schema: public; Owner: speedtest
--

CREATE SEQUENCE speedtest_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

-- Commented out the following line because it assumes the user of the speedtest server, @bplower
-- ALTER TABLE speedtest_users_id_seq OWNER TO speedtest; 

--
-- Name: speedtest_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: speedtest
--

ALTER SEQUENCE speedtest_users_id_seq OWNED BY speedtest_users.id;


--
-- Name: speedtest_users id; Type: DEFAULT; Schema: public; Owner: speedtest
--

ALTER TABLE ONLY speedtest_users ALTER COLUMN id SET DEFAULT nextval('speedtest_users_id_seq'::regclass);


--
-- Data for Name: speedtest_users; Type: TABLE DATA; Schema: public; Owner: speedtest
--

COPY speedtest_users (id, "timestamp", ip, ua, lang, dl, ul, ping, jitter, log) FROM stdin;
\.


--
-- Name: speedtest_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: speedtest
--

SELECT pg_catalog.setval('speedtest_users_id_seq', 1, true);


--
-- Name: speedtest_users speedtest_users_pkey; Type: CONSTRAINT; Schema: public; Owner: speedtest
--

ALTER TABLE ONLY speedtest_users
    ADD CONSTRAINT speedtest_users_pkey PRIMARY KEY (id);


--
-- PostgreSQL database dump complete
--

