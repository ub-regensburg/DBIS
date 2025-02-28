import time
import os
import os.path
from datetime import datetime
import json
from dotenv import load_dotenv, dotenv_values, find_dotenv
from elasticsearch import Elasticsearch, helpers
import psycopg2
from psycopg2.extras import RealDictCursor
import mariadb
from distutils.util import strtobool
from pathlib import Path
import argparse
import re


GLOBAL_INDEX_NAME = "dbis_index"

DEBUG = False


def setup_database_connection(config):
    IS_PRODUCTIVE = bool(config["PRODUCTIVE"] == 'true')

    host = None

    if (IS_PRODUCTIVE):
        host = config["PRODUCTIVE_DBIS_DB_HOST"]
    else:
        host = config["DBIS_DB_HOST"]

    try:
        connection = psycopg2.connect(
            database=config["DBIS_DB_DBNAME"],
            host=host,
            user=config["DBIS_DB_USER"],
            password=config["DBIS_DB_PASSWORD"],
            port=int(config["DBIS_DB_PORT"]),
            cursor_factory=RealDictCursor,
        )
    except:
        print("Connection to the dbis database was not successfull.")

        return None


    return connection


def setup_sus_connection(config):
    connection = mariadb.connect(
        user=config["UBR_DB_USER"],
        password=config["UBR_DB_PASSWORD"],
        host=config["UBR_DB_HOST"],
        port=int(config["UBR_DB_PORT"]),
        database=config["UBR_DB_DBNAME"],
    )

    return connection


def setup_elasticsearch(config):
    IS_PRODUCTIVE = bool(config["PRODUCTIVE"] == 'true')

    connect_string =  "https://127.0.0.1:9200" if IS_PRODUCTIVE else "http://elasticsearch:9200"

    client = None

    if IS_PRODUCTIVE:
        client = Elasticsearch(connect_string, ca_certs=config["ELASTIC_CERT"], basic_auth=("elastic", config["ELASTIC_KEY"]))
    else:
        client = Elasticsearch(connect_string)

    return client


def load_accesses(cursor_database):
    accesses = dict()

    access_sql = f"select access.license as license_id, JSONB_AGG(access.*) as accesses from access group by license_id;"

    cursor_database.execute(access_sql)

    access_results = cursor_database.fetchall()

    for result in access_results:
        license_id = int(result['license_id'])
        accesses[license_id] = result['accesses']

    return accesses

def load_license_types(cursor_database):
    types = dict()

    types_sql = f"select license_type.id, TO_JSONB(license_type.*) as license_type from license_type;"

    cursor_database.execute(types_sql)

    types_results = cursor_database.fetchall()

    for result in types_results:
        type_id = int(result['id'])
        types[type_id] = result['license_type']

    return types

def load_license_forms(cursor_database):
    forms = dict()

    forms_sql = f"select license_form.id, TO_JSONB(license_form.*) as license_form from license_form;"

    cursor_database.execute(forms_sql)

    forms_results = cursor_database.fetchall()

    for result in forms_results:
        type_id = int(result['id'])
        forms[type_id] = result['license_form']

    return forms


def get_resources(cursor_database):
    cursor_database.execute(
        "SELECT resource.id AS resource_id, resource.title::text AS resource_title, resource.description::text AS resource_description, resource.description_short::text, resource.report_time_start, resource.publication_time_start, resource.report_time_end, resource.publication_time_end, resource.isbn_issn, resource.is_visible, resource.created_at, resource.is_free FROM resource"
    )

    resources = cursor_database.fetchall()

    return resources


def get_resource_localisation(cursor_database, ubr_id, resource_id):
    cursor_database.execute(
        f"select COALESCE(jsonb_agg(DISTINCT resource_localisation.*), '[]')::text as resource_localisations from resource_localisation where (resource_localisation.organisation = '{ubr_id}') and resource_localisation.resource = {resource_id};"
    )

    results = cursor_database.fetchall()
    resource_localisations = json.loads(results[0]["resource_localisations"])

    return resource_localisations


def get_keywords(cursor_database, ubr_id, resource_id, is_global):
    if is_global:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct keyword.*) filter (where keyword.id is not null), '[]')::text as keywords from keyword left join keyword_for_resource on keyword.id = keyword_for_resource.keyword where (keyword_for_resource.organisation is null) and keyword_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["keywords"])
    else:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct keyword.*) filter (where keyword.id is not null), '[]')::text as keywords from keyword left join keyword_for_resource on keyword.id = keyword_for_resource.keyword where (keyword_for_resource.organisation = '{ubr_id}') and keyword_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["keywords"])


def get_authors(cursor_database, ubr_id, resource_id, is_global):
    if is_global:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct author.*) filter (where author.id is not null), '[]')::text as authors from author left join author_for_resource on author.id = author_for_resource.author where (author_for_resource.organisation is null) and author_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["authors"])
    else:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct author.*) filter (where author.id is not null), '[]')::text as authors from author left join author_for_resource on author.id = author_for_resource.author where (author_for_resource.organisation = '{ubr_id}') and author_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["authors"])


def get_subjects(cursor_database, ubr_id, resource_id, is_global):
    if is_global:
        if ubr_id is None:
            is_top_database = False

            cursor_database.execute(
                f"select coalesce(jsonb_agg(distinct subject.*) filter (where subject.id is not null), '[]')::text as subjects from subject left join subject_for_resource on subject.id = subject_for_resource.subject where subject_for_resource.organisation is null and subject_for_resource.resource = {resource_id};"
            )

            results = cursor_database.fetchall()
            results_subjects = json.loads(results[0]["subjects"])

            results_subjects_with_top_databases = []

            for subject in results_subjects:
                cursor_database.execute(
                    f"select top_resource_for_subject.sort_order from subject join top_resource_for_subject on subject.id = top_resource_for_subject.subject where top_resource_for_subject.organization is null and top_resource_for_subject.resource = {resource_id} and subject.id = {subject['id']};"
                )

                results = cursor_database.fetchall()

                subject['is_top_database'] = True if len(results) > 0 else False

                if subject['is_top_database']:
                    subject['sort_order'] = results[0]['sort_order']
                    is_top_database = True
                else:
                    subject['sort_order'] = 100000

                results_subjects_with_top_databases.append(subject)

            return results_subjects_with_top_databases, is_top_database
        else:
            is_top_database = False

            cursor_database.execute(
                f"select coalesce(jsonb_agg(distinct subject.*) filter (where subject.id is not null), '[]')::text as subjects from subject left join subject_for_resource on subject.id = subject_for_resource.subject left join subject_hidden_for_organisation on subject.id = subject_hidden_for_organisation.subject and subject_hidden_for_organisation.organisation = '{ubr_id}' where subject_for_resource.organisation is null and subject_hidden_for_organisation.subject is null and subject_for_resource.resource = {resource_id};"
            )

            results = cursor_database.fetchall()
            results_subjects = json.loads(results[0]["subjects"])

            results_subjects_with_top_databases = []

            for subject in results_subjects:
                cursor_database.execute(
                    f"select top_resource_for_subject.sort_order from subject join top_resource_for_subject on subject.id = top_resource_for_subject.subject where top_resource_for_subject.organization = '{ubr_id}' and top_resource_for_subject.resource = {resource_id} and subject.id = {subject['id']};"
                )

                results = cursor_database.fetchall()

                subject['is_top_database'] = True if len(results) > 0 else False 

                if subject['is_top_database']:
                    subject['sort_order'] = results[0]['sort_order']
                    is_top_database = True
                else:
                    subject['sort_order'] = 100000

                results_subjects_with_top_databases.append(subject)

            return results_subjects_with_top_databases, is_top_database
    else:
        is_top_database = False

        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct subject.*) filter (where subject.id is not null), '[]')::text as subjects from subject left join subject_for_resource on subject.id = subject_for_resource.subject where (subject_for_resource.organisation = '{ubr_id}') and subject_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()
        results_subjects = json.loads(results[0]["subjects"])

        results_subjects_with_top_databases = []

        for subject in results_subjects:
            cursor_database.execute(
                f"select top_resource_for_subject.sort_order from subject join top_resource_for_subject on subject.id = top_resource_for_subject.subject where top_resource_for_subject.organization = '{ubr_id}' and top_resource_for_subject.resource = {resource_id} and subject.id = {subject['id']};"
            )

            results = cursor_database.fetchall()

            subject['is_top_database'] = True if len(results) > 0 else False               

            if subject['is_top_database']:
                subject['sort_order'] = results[0]['sort_order']
                is_top_database = True
            else:
                subject['sort_order'] = 100000

            results_subjects_with_top_databases.append(subject)

        return results_subjects_with_top_databases, is_top_database


def get_collections_as_subjects(cursor_database, ubr_id, resource_id):
    is_top_database = False

    cursor_database.execute(
        f"select coalesce(jsonb_agg(distinct collection.*) filter (where collection.id is not null), '[]')::text as collections from collection left join resource_for_collection on collection.id = resource_for_collection.collection left join collection_for_organisation on collection.id = collection_for_organisation.collection where collection_for_organisation.organisation = '{ubr_id}' and collection.is_subject is true and resource_for_collection.resource = {resource_id};"
    )

    results = cursor_database.fetchall()
    results_collections = json.loads(results[0]["collections"])

    results_collections_with_top_databases = []

    for collection in results_collections:
        cursor_database.execute(
            f"select top_resource_for_collection.sort_order from collection join top_resource_for_collection on collection.id = top_resource_for_collection.collection where top_resource_for_collection.organization = '{ubr_id}' and top_resource_for_collection.resource = {resource_id} and collection.id = {collection['id']};"
        )

        results = cursor_database.fetchall()

        collection['is_top_database'] = True if len(results) > 0 else False

        if collection['is_top_database']:
            collection['sort_order'] = results[0]['sort_order']
            is_top_database = True
        else:
            collection['sort_order'] = 100000

        results_collections_with_top_databases.append(collection)

    return results_collections_with_top_databases, is_top_database

def get_collections(cursor_database, ubr_id, resource_id):
    is_top_database = False

    cursor_database.execute(
        f"select coalesce(jsonb_agg(distinct collection.*) filter (where collection.id is not null), '[]')::text as collections from collection left join resource_for_collection on collection.id = resource_for_collection.collection left join collection_for_organisation on collection.id = collection_for_organisation.collection where collection_for_organisation.organisation = '{ubr_id}' and resource_for_collection.resource = {resource_id};"
    )

    results = cursor_database.fetchall()
    results_collections = json.loads(results[0]["collections"])

    results_collections_with_top_databases = []

    for collection in results_collections:
        cursor_database.execute(
            f"select top_resource_for_collection.sort_order from collection join top_resource_for_collection on collection.id = top_resource_for_collection.collection where top_resource_for_collection.resource = {resource_id} and top_resource_for_collection.organization = '{ubr_id}';"
        )

        results = cursor_database.fetchall()

        collection['is_top_database'] = True if len(results) > 0 else False

        if collection['is_top_database']:
            collection['sort_order'] = results[0]['sort_order']
            is_top_database = True
        else:
            collection['sort_order'] = 100000

        results_collections_with_top_databases.append(collection)

    return results_collections_with_top_databases, is_top_database


def get_countries(cursor_database, ubr_id, resource_id, is_global):
    if is_global:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct country.*) filter (where country.id is not null), '[]')::text as countries from country left join country_for_resource on country.id = country_for_resource.country where (country_for_resource.organisation is null) and country_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["countries"])
    else:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct country.*) filter (where country.id is not null), '[]')::text as countries from country left join country_for_resource on country.id = country_for_resource.country where (country_for_resource.organisation = '{ubr_id}') and country_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["countries"])


def get_resource_types(cursor_database, ubr_id, resource_id, is_global):
    if is_global:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct resource_type.*) filter (where resource_type.id is not null), '[]')::text as resource_types from resource_type left join resource_type_for_resource on resource_type.id = resource_type_for_resource.resource_type where (resource_type_for_resource.organisation is null) and resource_type_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["resource_types"])
    else:
        cursor_database.execute(
            f"select coalesce(jsonb_agg(distinct resource_type.*) filter (where resource_type.id is not null), '[]')::text as resource_types from resource_type left join resource_type_for_resource on resource_type.id = resource_type_for_resource.resource_type where (resource_type_for_resource.organisation = '{ubr_id}') and resource_type_for_resource.resource = {resource_id};"
        )

        results = cursor_database.fetchall()

        return json.loads(results[0]["resource_types"])


def get_alternative_titles(cursor_database, ubr_id, resource_id):
    cursor_database.execute(
        f"select COALESCE(jsonb_agg(DISTINCT alternative_title.*), '[]')::text as alternative_titles from alternative_title where alternative_title.resource = {resource_id};"
    )

    results = cursor_database.fetchall()
    return json.loads(results[0]["alternative_titles"])

def update_frequencies(cursor_database, ubr_id, resource_id, is_global):
    cursor_database.execute(
        f"select coalesce(jsonb_agg(distinct update_frequency.*) filter (where update_frequency.id is not null), '[]')::text as update_frequencies from update_frequency left join resource on resource.update_frequency = update_frequency.id where resource.id = {resource_id};"
    )

    results = cursor_database.fetchall()

    return json.loads(results[0]["update_frequencies"])

def get_licenses(cursor_database, ubr_id, resource_id, accesses):
    if (DEBUG):
        print(f"Insert license with {ubr_id} and for resource {resource_id}")

    licenses = []

    if ubr_id:
        sql = f"select license.* from license left join license_for_organization on license_for_organization.license = license.id where license.resource = {resource_id} and license_for_organization.organization = '{ubr_id}' and license.is_active = true group by license.id;"

        cursor_database.execute(sql)

        results = cursor_database.fetchall()

        for license in results:
            license_id = license['id']
            publication_form_id = license['publication_form']

            license_type = int(license['type'])

            if license_type == 1:
                license['is_global'] = True
            else:
                license['is_global'] = False

            access_sql = f"select JSONB_AGG(access.*) as accesses from access LEFT JOIN access_hidden_for_organisation ON access.id = access_hidden_for_organisation.access AND access_hidden_for_organisation.organisation = '{ubr_id}' where access.license = {license_id} and (access.organization = '{ubr_id}' or access.organization is null) and access_hidden_for_organisation.access is null;"
            cursor_database.execute(access_sql)
            access_results = cursor_database.fetchall()

            license['accesses'] = access_results[0]['accesses']
            if license['accesses']:
                for access in license['accesses']:
                    access['isMainAccess'] = False
                    sql_access = f"select count(*) from main_access_for_organization where main_access_for_organization.organization ='{ubr_id}' and main_access_for_organization.resource = {resource_id} and main_access_for_organization.access = {access['id']};"
                    cursor_database.execute(sql_access)
                    count_result = cursor_database.fetchone()
                    if count_result['count'] > 0:
                        access['isMainAccess'] = True

            publication_form = None
            if publication_form_id:
                publication_form_sql = f"select TO_JSONB(publication_form.*) as publication_form_obj from publication_form where publication_form.id = {publication_form_id};"
                cursor_database.execute(publication_form_sql)
                publication_form_result = cursor_database.fetchone()
                publication_form = publication_form_result['publication_form_obj']
            
            license['publication_form'] = publication_form
            
            """
            license_id = int(license['id'])
            
            if license_id in accesses:
                license['accesses'] = accesses[license_id]
            """
            
            licenses.append(license)

    else:
        # TODO: Check the country: If f.ex. Germany, then license.type = 3 needs to be added
        sql = f"select license.* from license where license.type = 1 and license.resource = {resource_id} and license.is_active = true;"

        cursor_database.execute(sql)

        results = cursor_database.fetchall()

        for license in results:
            license_id = license['id']
            publication_form_id = license['publication_form']

            license['is_global'] = True

            access_sql = f"select JSONB_AGG(access.*) as accesses from access where access.license = {license_id} and access.organization is null;"

            cursor_database.execute(access_sql)

            access_results = cursor_database.fetchall()
            
            license['accesses'] = access_results[0]['accesses']
            if license['accesses']:
                for access in license['accesses']:
                    access['isMainAccess'] = False

            publication_form = None
            if publication_form_id:
                publication_form_sql = f"select TO_JSONB(publication_form.*) as publication_form_obj from publication_form where publication_form.id = {publication_form_id};"
                cursor_database.execute(publication_form_sql)
                publication_form_result = cursor_database.fetchone()
                publication_form = publication_form_result['publication_form_obj']

            license['publication_form'] = publication_form
            
            """
            license_id = int(license['id'])

            if license_id in accesses:
                license['accesses'] = accesses[license_id]
            """

            licenses.append(license)

    return licenses

def _generate_documents_for_global_index(cursor_database, resources, accesses):
    for resource in resources:
        resource_id = resource["resource_id"]

        ubr_id = None

        keywords = get_keywords(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=True
        )

        authors = get_authors(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=True
        )

        alternative_titles = get_alternative_titles(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id
        )

        subjects, is_top_database = get_subjects(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=True
        )

        resource_types = get_resource_types(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=True
        )

        countries = get_countries(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=True
        )

        resource_localisations = []

        licenses = get_licenses(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            accesses=accesses
        )

        doc = {
            "resource_id": resource_id,
            "resource_title": resource["resource_title"],
            "alternative_titles": alternative_titles,
            "description": json.loads(resource["resource_description"]) if resource["resource_description"] is not None else None,
            "description_short": json.loads(resource["description_short"]) if resource["description_short"] is not None else None,
            "report_time_start": resource["report_time_start"],
            "report_time_end": resource["report_time_end"],
            "publication_time_start": resource["publication_time_start"],
            "publication_time_end": resource["publication_time_end"],
            "isbn_issn": resource["isbn_issn"],
            "is_visible": resource["is_visible"],
            "is_free": resource["is_free"],
            "resource_localisations": resource_localisations,
            "keywords": keywords,
            "authors": authors,
            "subjects": subjects,
            "is_top_database": is_top_database,
            "resource_types": resource_types,
            "countries": countries,
            "licenses": licenses,
            "created_at": resource["created_at"].strftime("%Y-%m-%d"),
            "timestamp": datetime.now()
        }

        if (DEBUG):
            print("Insert document in global index\n")
            print(doc)

        yield {'_index': GLOBAL_INDEX_NAME, '_id': resource_id,                                  
              '_source': doc}

def _generate_documents_for_local_index(cursor_database, ubr_id, resources, accesses):
    for resource in resources:
        resource_id = resource["resource_id"]

        index_name = f"{ubr_id.lower()}_index"

        keywords = get_keywords(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=False
        )

        if len(keywords) < 1:
            keywords = get_keywords(
                cursor_database=cursor_database,
                ubr_id=None,
                resource_id=resource_id,
                is_global=True
            )

        authors = get_authors(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=False
        )

        if len(authors) < 1:
            authors = get_authors(
                cursor_database=cursor_database,
                ubr_id=None,
                resource_id=resource_id,
                is_global=True
            )

        alternative_titles = get_alternative_titles(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id
        )            

        # Here is the problem that some databases are not associated to certain (global) subjects as local subjects oberwrite global subjects
        # And collections that are displayed as subjects also lead to hidding global subjects
        subjects, is_top_database_in_subjects = get_subjects(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=False
        )

        if len(subjects) < 1:
            subjects, is_top_database_in_subjects = get_subjects(
                cursor_database=cursor_database,
                ubr_id=ubr_id,
                resource_id=resource_id,
                is_global=True
            )

        collections_as_subjects, is_top_database_in_collections_as_subjects = get_collections_as_subjects(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id
        )
            
        collections, is_top_database_for_collection = get_collections(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id
        )
            
        resource_types = get_resource_types(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=False
        )

        if len(resource_types) < 1:
            resource_types = get_resource_types(
                cursor_database=cursor_database,
                ubr_id=ubr_id,
                resource_id=resource_id,
                is_global=True
        )

        countries = get_countries(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            is_global=False
        )

        if len(countries) < 1:
            countries = get_countries(
                cursor_database=cursor_database,
                ubr_id=ubr_id,
                resource_id=resource_id,
                is_global=True
        )

        resource_localisations = get_resource_localisation(cursor_database=cursor_database,
                ubr_id=ubr_id,
                resource_id=resource_id)
                
        licenses = get_licenses(
            cursor_database=cursor_database,
            ubr_id=ubr_id,
            resource_id=resource_id,
            accesses=accesses
        )

        is_visible_locally = True if (len(resource_localisations) > 0 and resource_localisations[0]["is_visible"] is True and resource["is_visible"] is True) or ((len(resource_localisations) <  1 or resource_localisations[0]["is_visible"] is None) and resource["is_visible"] is True) else False

        doc = {
            "resource_id": resource_id,
            "resource_title": resource["resource_title"],
            "alternative_titles": alternative_titles,
            "description": json.loads(resource["resource_description"]) if resource["resource_description"] is not None else None,
            "description_short": json.loads(resource["description_short"]) if resource["description_short"] is not None else None,
            "report_time_start": resource["report_time_start"],
            "report_time_end": resource["report_time_end"],
            "publication_time_start": resource["publication_time_start"],
            "publication_time_end": resource["publication_time_end"],
            "isbn_issn": resource["isbn_issn"],
            "is_visible": resource["is_visible"],
            "is_visible_locally": is_visible_locally,
            "is_free": resource["is_free"],
            "resource_localisations": resource_localisations,
            "keywords": keywords,
            "authors": authors,
            "subjects": subjects + collections_as_subjects,
            "collections": collections,
            "is_top_database": True if is_top_database_in_subjects or is_top_database_in_collections_as_subjects else False,
            "resource_types": resource_types,
            "countries": countries,
            "licenses": licenses,
            "created_at": resource["created_at"].strftime("%Y-%m-%d"),
            "timestamp": datetime.now()
        }

        if (DEBUG):
            print("Insert document in local index\n")
            print(doc)

        yield {'_index': index_name, '_id': resource_id,                                  
              '_source': doc}

def insert_documents(connection_database, client, ubr_id=None):
    cursor_database = connection_database.cursor()

    resources = get_resources(cursor_database)

    accesses = load_accesses(cursor_database)

    types = load_license_types(cursor_database)

    forms = load_license_forms(cursor_database)
    
    if ubr_id:
        # Local index
        ubr_id = ubr_id.upper()

        index_local_start = datetime.now()
        print(index_local_start, f"Start inserting documents for {ubr_id} ...")
        try:
            for status_ok, response in helpers.streaming_bulk(client, actions=_generate_documents_for_local_index(cursor_database, ubr_id, resources, accesses), chunk_size=1000, request_timeout=120):
                if not status_ok:                                                                                                    
                    print(response)  
        except helpers.BulkIndexError as e:
            print(f"Bulk index error: {len(e.errors)} documents failed.")

            # Print detailed errors (show first 5 failed documents for debugging)
            for error in e.errors[:5]:
                print("Failed document:", error)

        index_local_end = datetime.now()
        index_local_duration = index_local_end - index_local_start
        print(index_local_end, f"Finished inserting documents for {ubr_id}: ", index_local_duration)
    else:
        # Global index
        index_global_start = datetime.now()
        print(index_global_start, f"Start inserting documents for the global index ...")
        try:
            for status_ok, response in helpers.streaming_bulk(client, actions=_generate_documents_for_global_index(cursor_database, resources, accesses), chunk_size=1000, request_timeout=120):
                if not status_ok:                                                                                                    
                    print(response)  
        except helpers.BulkIndexError as e:
            print(f"Bulk index error: {len(e.errors)} documents failed.")

            # Print detailed errors (show first 5 failed documents for debugging)
            for error in e.errors[:5]:
                print("Failed document:", error)

        index_global_end = datetime.now()
        index_global_duration = index_global_end - index_global_start
        print(index_global_end, f"Finished inserting documents for the global index: ", index_global_duration)


def create_index(connection_sus, connection_database, client, ubr_id=None):
    body = {
        "settings": {
            "analysis": {              
                "analyzer": {
                    "dbis_analyzer": {
                        "type": "custom",
                        "tokenizer": "standard",
                        "filter": ["lowercase", "asciifolding", "german_normalization", "word_delimiter_graph_filter", "flatten_graph"]
                    },
                    "phrase_analyzer": {
                        "type": "custom",                      
                        "tokenizer": "standard",
                        "filter": ["lowercase", "asciifolding", "german_normalization"]
                    }                                  
                },            
                "filter":{
                    "german_normalization": {
                        "type": "german_normalization"
                    },
                    "word_delimiter_graph_filter": {
                        "type": "word_delimiter_graph",
                        "preserve_original": True,  
                        "split_on_case_change": True,  
                        "split_on_numerics": True,  
                        "generate_word_parts": True,  
                        "generate_number_parts": True,  
                        "catenate_words": True,  
                        "catenate_numbers": True,  
                        "catenate_all": True  
                    }                                      
                }            
            }
        },
        'mappings': {
            "properties": {
                "resource_title": {
                    "type": "text",
                    "analyzer":"dbis_analyzer",
                    "search_analyzer": "phrase_analyzer",
                    "fields": {
                        "keyword": {
                        "type": "keyword",
                        "ignore_above": 256
                        }
                    }                    
                },
                "resource_localisations": {
                    "properties": {
                        "note": {
                            "properties": {
                                "de": {
                                    "type": "text",
                                    "analyzer": "german"
                                },
                                "en": {
                                    "type": "text",
                                    "analyzer": "english"
                                }
                            }
                        }
                    }
                },                 
                "description": {
                    "properties": {
                        "de": {
                            "type": "text",
                            "analyzer": "german"
                        },
                        "en": {
                            "type": "text",
                            "analyzer": "english"
                        }
                    }
                },
                "description_short": {
                    "properties": {
                        "de": {
                            "type": "text",
                            "analyzer": "german"
                        },
                        "en": {
                            "type": "text",
                            "analyzer": "english"
                        }
                    }
                }                
            }
        }
    }

    print(f"Start deleting and creating global index ...")
    
    client.indices.delete(index="dbis_index", ignore_unavailable=True)
    client.indices.create(index="dbis_index", body=body, request_timeout=60)

    insert_documents(connection_database=connection_database, client=client, ubr_id=None)

    if ubr_id:
        ubr_id = ubr_id.lower()

        if re.search(r"\s", ubr_id):
            print(f"Org name {ubr_id} contains whitespace")
            return

        print(f"Start deleting and creating index for {ubr_id} ...")

        index_name = f"{ubr_id}_index"
        client.indices.delete(index=index_name, ignore_unavailable=True)
        client.indices.create(index=index_name, body=body, request_timeout=60)

        insert_documents(connection_database=connection_database, client=client, ubr_id=ubr_id)
    else:
        cursor_sus = connection_sus.cursor(dictionary=True)

        cursor_sus.execute("SELECT * FROM Organisations")

        organisations = cursor_sus.fetchall()

        for organisation in organisations:
            ubr_id = organisation["ubr_id"]
            ubr_id = ubr_id.lower()

            if re.search(r"\s", ubr_id):
                continue
            
            print(f"Start deleteting and creating index for {ubr_id} ...")

            index_name = f"{ubr_id}_index"
            client.indices.delete(index=index_name, ignore_unavailable=True)
            client.indices.create(index=index_name, body=body, request_timeout=60)

            insert_documents(connection_database=connection_database, client=client, ubr_id=ubr_id)


def main():
    # env_file = find_dotenv(raise_error_if_not_found=True, usecwd=False)
    env_file = f'{Path(__file__).parent}/.env'

    config = dotenv_values(dotenv_path=env_file)

    connection_database = setup_database_connection(config)

    if connection_database is None:
        exit()

    connection_sus = setup_sus_connection(config)

    if connection_sus is None:
        exit()

    parser = argparse.ArgumentParser(description="A script to create all indices or just a specific index for DBIS organzations.")
    subparsers = parser.add_subparsers(dest='command', help='commands')

    parser_greet = subparsers.add_parser('index', help='Create an index')
    parser_greet.add_argument('organization', type=str, help='Ubr id of the organization or "all" for all organizations')

    args_parsed = parser.parse_args()

    if args_parsed.command == 'index':
        ubr_id = None if args_parsed.organization.lower() == 'all' else args_parsed.organization

        print(f'Process command line option {ubr_id}')

        client = setup_elasticsearch(config)

        create_index(connection_sus=connection_sus, connection_database=connection_database, client=client, ubr_id=ubr_id)
    else:
        parser.print_help()
                                       
    connection_database.close()
    connection_sus.close()

"""
Call python indexer.py index ubr
"""
if __name__ == "__main__":
    main()