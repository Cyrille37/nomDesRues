# Noms des Rues

Déterminer le genre et la thématique des noms des rues d'une ville.

- genre: Féminin / Masculin
- type: humain, lieu, événement, ...
- thématique: poëte, ingénieur, politicien, ...

## La base de données

La liste des "natures" avec leur nombre d'occurences:
```
SELECT n.label, count(*) c FROM natures n
LEFT JOIN streetsshortnames_has_natures sshn ON (sshn.natures_id=n.id)
GROUP BY n.id ORDER BY c DESC
```

Les genres des natures des noms courts:
```
SELECT  nom, wikidata_wd, wikidata_genre FROM streetsshortnames_has_natures sshn
LEFT JOIN streetsshortnames ss ON (ss.id=sshn.streetsshortnames_id)
WHERE wikidata_genre IS NOT NULL
```


## Les scripts

### import_BANO.php

Construit une Db sqlite à partir d'un fichier BANO.

`$ ./import_BANO.php /home/cyrille/DATA/BANO/BAN_odbl_37.csv`

### search_streetsname_wikidata.php

Recherche les rues dans une base WikiData.

`$ ./search_streetsname_wikidata.php [reset data]`


## Sources des données

- Noms des rues: [BANO](http://wiki.openstreetmap.org/wiki/WikiProject_France/WikiProject_Base_Adresses_Nationale_Ouverte_(BANO))
- Genre / Type / Thématiques
 - Wikipedia [API:Search](https://www.mediawiki.org/wiki/API:Search)
 - WikiData [Query console](https://query.wikidata.org/)
 - DBPedia ?
 - OpenStreetMap highway's "description" tag
  - Il y en a pas beaucoup de renseigné...
  - Représentent-ils toujours une description du nom ?
 - ?
 - Google Knowledge base [Google inside search](https://www.google.com/intl/bn/insidesearch/features/search/knowledge.html), [Google Knowledge Graph Search API](https://developers.google.com/knowledge-graph/)

### BANO

Les colonnes:
`id,nom_voie,id_fantoir,numero,rep,code_insee,code_post,alias,nom_ld,x,y,commune,fant_voie,fant_ld,lat,lon`


## Divers doc

- SPARQL
 - [Le tutoriel SPARQL](http://web-semantique.developpez.com/tutoriels/jena/arq/introduction-sparql/)
- WikiData
 - [Deploy standalone service](https://www.mediawiki.org/wiki/Wikidata_query_service/User_Manual#Standalone_service)
 - [Getting Started](https://github.com/wikimedia/wikidata-query-rdf/blob/master/docs/getting-started.md)
 - [Wikidata:Accès aux données](https://www.wikidata.org/wiki/Wikidata:Data_access/fr)

### Install WikiData Query

apt-get update
apt-get upgrade
apt-get install vim openjdk-8-jdk-headless maven git
wget http://repo1.maven.org/maven2/org/wikidata/query/rdf/service/0.1.0/service-0.1.0-dist.zip
unzip service-0.1.0-dist.zip
cd service-0.1.0
wget https://dumps.wikimedia.org/wikidatawiki/entities/latest-all.json.bz2

