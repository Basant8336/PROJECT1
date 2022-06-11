<?php

/**
 * Description of DataService
 *
 * @author satish
 */
if (!defined('sugarEntry'))
    define('sugarEntry', true);

$rootDir = getcwd();

require_once $rootDir . '/include/entryPoint.php';

class DataService {

    public static function getProductIdByItemIdAndAccountId($accountId, $itemId) {
        global $db;

        $sql = "SELECT p.id prod_id,p.name pro_name,p.quantity,a.billing_address_state state FROM products p
        INNER JOIN products_cstm pc ON pc.id_c = p.id
        INNER JOIN accounts a ON p.account_id = a.id AND a.deleted = 0
        WHERE p.deleted = 0
        AND pc.product_status = 'hrn' AND p.status = 'Renewed'
        AND p.mft_part_num = '" . $itemId . "' AND a.id = '" . $accountId . "'";

        $query = $db->query($sql);
        $result = $db->fetchByAssoc($query);
        return $result;
    }

    public static function getAccountIdByIntacctId($intacctId) {
        global $db;
        $sql = "SELECT id_c FROM accounts_cstm WHERE intacct_id='" . $intacctId . "'";
        $query = $db->query($sql);
        $result = $db->fetchByAssoc($query);
        return $result['id_c'];
    }

    public static function updateRenewalDate($prodId) {
        global $db;

        $selectSQL = "SELECT quantity,date_purchased,name,date_support_expires, date_add(date_support_expires, INTERVAL 1 year) new_date FROM products WHERE id='" . $prodId . "'";
        $selectQuery = $db->query($selectSQL);
        $selectResult = $db->fetchByAssoc($selectQuery);
        

        $sql = "UPDATE products INNER JOIN products_cstm ON id = id_c SET date_support_expires = date_add(date_support_expires, INTERVAL 1 year) WHERE id='" . $prodId . "'";

        $db->query($sql);
        return $selectResult;
    }

    /**
     * Get Id from sugar for newly created accounts
     * @global type $db
     * @return type
     */
    public static function getNewAccounts() {
        global $db;
        $accSQL = "SELECT distinct(a.id),a.name,a.billing_address_state,a.billing_address_city,a.billing_address_postalcode,ac.acc_charter_number_c FROM accounts a
INNER JOIN accounts_cstm ac ON ac.id_c = a.id
INNER JOIN products p ON p.account_id = a.id AND p.deleted=0
INNER JOIN products_cstm pc ON pc.id_c=p.id
WHERE a.deleted=0 AND (ac.intacct_id = '0' OR ac.intacct_id IS NULL) AND p.status IN ('Sold', 'Renewed') ORDER BY a.name";
        $accQuery = $db->query($accSQL);
        $accountIds = array();
        while ($accRow = $db->fetchByAssoc($accQuery)) {
            $accountIds[] = $accRow['id'];
        }
        return $accountIds;
    }

    /**
     * Run the SQL and return the data array
     * @global type Object $db
     * @param type String $sql
     * @return type Array $dataArray
     */
    private static function getDataArray($sql) {
        global $db;
        $result = $db->query($sql);
        $dataArray = array();
        while ($row = $db->fetchByAssoc($result)) {
            $accountId = $row['account_id'];
            $dataArray[$accountId][] = $row;
        }

        return $dataArray;
    }

    public static function getCUSGTechnologyMonthlyBillingReport($startDate, $endDate) {
        echo $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
            products.date_support_expires date_support_expires,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
			products.discount_amount,products.cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(contacts.first_name, ''),' ',IFNULL(contacts.last_name, '')))) contact_name,
            contacts_cstm.person_type_c as person_type,
            addr.email_address
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
            LEFT JOIN
            contacts contacts ON products.contact_id = contacts.id AND contacts.deleted = 0
            LEFT JOIN 
            contacts_cstm contacts_cstm ON contacts.id = contacts_cstm.id_c 
            LEFT JOIN
            email_addr_bean_rel rel ON products.contact_id = rel.bean_id AND rel.primary_address = 1 AND rel.deleted = 0
            LEFT JOIN
            email_addresses addr ON addr.id = rel.email_address_id AND addr.deleted = 0
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            (((products.date_support_expires >= " . "'$startDate'" .
            " AND products.date_support_expires <= " . "'$endDate'" . " )
            AND (products.status = 'Renewed')
            AND (l1.name LIKE '%CUSG - Technology Solutions%')
            AND (products.cost_price > 0)
            AND (products.name NOT LIKE '%ComplySight%')))
            AND products.deleted = 0 
            ORDER BY account_name ASC";


        return self::getDataArray($sql);
    }

    public static function getLifeStepsWalletCaseBillingsReport($startDate, $endDate) {
        $sql = "SELECT
            IFNULL(l2.name, '') account_name,
            IFNULL(l2.id, '') account_id,
            l2_cstm.intacct_id acc_intacct_id,
            l1.id timesheet_id,
            l1.executed_time executed_time,
            cases.case_number sugar_case_number,
            IFNULL(cases.name, '') sugar_subject,
            IFNULL(l1.description, '') timesheet_description,
            l4.name sugar_case_category,
            l1.total_cost timesheet_total_cost,
            IF(l1.time_to_resolve_hour = 0,1,l1.time_to_resolve_hour) time_to_resolve_hour,
            if(l1.time_to_resolve_hour > 0,(l1.total_cost * (1/l1.time_to_resolve_hour)),l1.total_cost) hourly_price,
            l1.billing_product_code billing_product_code,
            l2_cstm.acc_cuv_sales_rep_c cstm_acc_cuv_sales_rep_c,
            IFNULL(l5.id, '') user_id,
            CONCAT(IFNULL(l5.first_name, ''),' ',IFNULL(l5.last_name, '')) sugar_support_rep,
            l5.title user_title,
            IFNULL(l3.id, '') product_templates_id,
            l3.mft_part_num mft_part_num,
            l3.intacct_id pro_intacct_id,
            l3.intacct_department intacct_dept_id,
            pc.name category_name,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) intacct_emp_name

        FROM
            cases
                LEFT JOIN
            oss_timesheet l1 ON cases.id = l1.parent_id
                AND l1.deleted = 0
                AND l1.parent_type = 'Cases'
                LEFT JOIN
            accounts l2 ON cases.account_id = l2.id
                AND l2.deleted = 0
                LEFT JOIN
            producttemplates_oss_timesheet_1_c l3_1 ON l1.id = l3_1.producttemplates_oss_timesheet_1oss_timesheet_idb
                AND l3_1.deleted = 0
                LEFT JOIN
            product_templates l3 ON l3.id = l3_1.producttemplates_oss_timesheet_1producttemplates_ida
                AND l3.deleted = 0
                LEFT JOIN
            product_categories pc ON pc.id = l3.category_id
                AND pc.deleted = 0
                LEFT JOIN
            oss_case_category_cases_1_c l4_1 ON cases.id = l4_1.oss_case_category_cases_1cases_idb
                AND l4_1.deleted = 0
                LEFT JOIN
            oss_case_category l4 ON l4.id = l4_1.oss_case_category_cases_1oss_case_category_ida
                AND l4.deleted = 0
                LEFT JOIN
            users l5 ON cases.assigned_user_id = l5.id
                AND l5.deleted = 0
                LEFT JOIN
            accounts_cstm l2_cstm ON cases.account_id = l2_cstm.id_c
                LEFT JOIN
            users ON users.user_name = l2_cstm.acc_cuv_sales_rep_c
                AND users.deleted = 0 AND users.user_name <> ''

        WHERE
            (((l1.total_cost > 0)
                AND ((l1.executed_time >= '" . $startDate . "'
        AND l1.executed_time <= '" . $endDate . "'))
                AND (l4.id <> 'db38df73-ab71-9a07-138c-55f069d46cfc')))
                AND cases.deleted = 0
                AND (pc.name LIKE '%lifestep%')
                ORDER BY account_name ASC";
        //        print_r($sql);die;

        return self::getDataArray($sql);
    }


    public static function getLifeStepsWalletProductBillingsReport($startDate, $endDate) {
        $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
            products.date_support_expires date_support_expires,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
            products.discount_amount,products.cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            (((products.date_support_expires >= " . "'$startDate'" .
            " AND products.date_support_expires <= " . "'$endDate'" . " )
            AND (products.status = 'Renewed')
            AND (l1.name LIKE '%lifestep%')
            AND (products.cost_price > 0)))
            AND products.deleted = 0 ORDER BY account_name ASC";


        return self::getDataArray($sql);
    }

    public static function getCaseBillingReport($startDate, $endDate) {
        $sql = "SELECT
            IFNULL(l2.name, '') account_name,
            IFNULL(l2.id, '') account_id,
            l2_cstm.intacct_id acc_intacct_id,
            l1.id timesheet_id,
            l1.executed_time executed_time,
            cases.case_number sugar_case_number,
            IFNULL(cases.name, '') sugar_subject,
            IFNULL(l1.description, '') timesheet_description,
            l4.name sugar_case_category,
            l1.total_cost timesheet_total_cost,
            IF(l1.time_to_resolve_hour = 0,1,l1.time_to_resolve_hour) time_to_resolve_hour,
            if(l1.time_to_resolve_hour > 0,(l1.total_cost * (1/l1.time_to_resolve_hour)),l1.total_cost) hourly_price,
            l1.billing_product_code billing_product_code,
            l2_cstm.acc_cuv_sales_rep_c cstm_acc_cuv_sales_rep_c,
            IFNULL(l5.id, '') user_id,
            CONCAT(IFNULL(l5.first_name, ''),' ',IFNULL(l5.last_name, '')) sugar_support_rep,
            l5.title user_title,
            IFNULL(l3.id, '') product_templates_id,
            l3.mft_part_num mft_part_num,
            l3.intacct_id pro_intacct_id,
            l3.intacct_department intacct_dept_id,
            pc.name category_name,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) intacct_emp_name,
            cc.case_email_c as email_address

        FROM
            cases
                LEFT JOIN
            oss_timesheet l1 ON cases.id = l1.parent_id
                AND l1.deleted = 0
                AND l1.parent_type = 'Cases'
                LEFT JOIN
            accounts l2 ON cases.account_id = l2.id
                AND l2.deleted = 0
                LEFT JOIN
            producttemplates_oss_timesheet_1_c l3_1 ON l1.id = l3_1.producttemplates_oss_timesheet_1oss_timesheet_idb
                AND l3_1.deleted = 0
                LEFT JOIN
            product_templates l3 ON l3.id = l3_1.producttemplates_oss_timesheet_1producttemplates_ida
                AND l3.deleted = 0
                LEFT JOIN
            product_categories pc ON pc.id = l3.category_id
                AND pc.deleted = 0
                LEFT JOIN
            oss_case_category_cases_1_c l4_1 ON cases.id = l4_1.oss_case_category_cases_1cases_idb
                AND l4_1.deleted = 0
                LEFT JOIN
            oss_case_category l4 ON l4.id = l4_1.oss_case_category_cases_1oss_case_category_ida
                AND l4.deleted = 0
                LEFT JOIN
            users l5 ON cases.assigned_user_id = l5.id
                AND l5.deleted = 0
                LEFT JOIN
            accounts_cstm l2_cstm ON cases.account_id = l2_cstm.id_c
            LEFT JOIN 
            cases_cstm cc ON cc.id_c = cases.id
    
                LEFT JOIN
            users ON users.user_name = l2_cstm.acc_cuv_sales_rep_c
                AND users.deleted = 0 AND users.user_name <> ''

        WHERE
            (((l1.total_cost > 0)
                AND ((l1.executed_time >= '" . $startDate . "'
        AND l1.executed_time <= '" . $endDate . "'))
                AND (l4.id <> 'db38df73-ab71-9a07-138c-55f069d46cfc')))
                AND cases.deleted = 0
				AND (pc.name LIKE '%CUSG - Technology Solutions%')
                ORDER BY account_name ASC";
        //        print_r($sql);die;

        return self::getDataArray($sql);
    }

    public static function getCaseBillingMarketingReport($startDate, $endDate) {
        $sql = "SELECT
            IFNULL(l2.name, '') account_name,
            IFNULL(l2.id, '') account_id,
            l2_cstm.intacct_id acc_intacct_id,
            l1.id timesheet_id,
            l1.executed_time executed_time,
            cases.case_number sugar_case_number,
            IFNULL(cases.name, '') sugar_subject,
            IFNULL(l1.description, '') timesheet_description,
            l4.name sugar_case_category,
            l1.total_cost timesheet_total_cost,
            IF(l1.time_to_resolve_hour = 0,1,l1.time_to_resolve_hour) time_to_resolve_hour,
            if(l1.time_to_resolve_hour > 0,(l1.total_cost * (1/l1.time_to_resolve_hour)),l1.total_cost) hourly_price,
            l1.billing_product_code billing_product_code,
            l2_cstm.acc_cuv_sales_rep_c cstm_acc_cuv_sales_rep_c,
            IFNULL(l5.id, '') user_id,
            CONCAT(IFNULL(l5.first_name, ''),' ',IFNULL(l5.last_name, '')) sugar_support_rep,
            l5.title user_title,
            IFNULL(l3.id, '') product_templates_id,
            l3.mft_part_num mft_part_num,
            l3.intacct_id pro_intacct_id,
            l3.intacct_department intacct_dept_id,
            pc.name category_name,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) intacct_emp_name

        FROM
            cases
                LEFT JOIN
            oss_timesheet l1 ON cases.id = l1.parent_id
                AND l1.deleted = 0
                AND l1.parent_type = 'Cases'
                LEFT JOIN
            accounts l2 ON cases.account_id = l2.id
                AND l2.deleted = 0
                LEFT JOIN
            producttemplates_oss_timesheet_1_c l3_1 ON l1.id = l3_1.producttemplates_oss_timesheet_1oss_timesheet_idb
                AND l3_1.deleted = 0
                LEFT JOIN
            product_templates l3 ON l3.id = l3_1.producttemplates_oss_timesheet_1producttemplates_ida
                AND l3.deleted = 0
                LEFT JOIN
            product_categories pc ON pc.id = l3.category_id
                AND pc.deleted = 0
                LEFT JOIN
            oss_case_category_cases_1_c l4_1 ON cases.id = l4_1.oss_case_category_cases_1cases_idb
                AND l4_1.deleted = 0
                LEFT JOIN
            oss_case_category l4 ON l4.id = l4_1.oss_case_category_cases_1oss_case_category_ida
                AND l4.deleted = 0
                LEFT JOIN
            users l5 ON cases.assigned_user_id = l5.id
                AND l5.deleted = 0
                LEFT JOIN
            accounts_cstm l2_cstm ON cases.account_id = l2_cstm.id_c
                LEFT JOIN
            users ON users.user_name = l2_cstm.acc_cuv_sales_rep_c
                AND users.deleted = 0 AND users.user_name <> ''

        WHERE
            (((l1.total_cost > 0)
                AND ((l1.executed_time >= '" . $startDate . "'
        AND l1.executed_time <= '" . $endDate . "'))
                AND (l4.id <> 'db38df73-ab71-9a07-138c-55f069d46cfc')))
                AND cases.deleted = 0
				AND (pc.name LIKE '%marketing%')
                ORDER BY account_name ASC";
        //        print_r($sql);die;

        return self::getDataArray($sql);
    }


    public static function getStrategicServiceReport($startDate, $endDate) {
        $sql = "SELECT cases.name sugar_subject, CONCAT(IFNULL(l4.first_name, ''),' ',IFNULL(l4.last_name, '')) sugar_support_rep, l4.intacct_emp_id, l5.mft_part_num, IFNULL(l4.id,'') User_id , l4.first_name user_first_name, l4.last_name user_last_name, l4.title user_title, IFNULL(l2.id,'') account_id , IFNULL(l2.name,'') account_name , l2_cstm.intacct_id acc_intacct_id, IFNULL(l3.name, '') sugar_case_category, IFNULL(l1.id,'') timesheet_id , l1.billing_product_code timesheet_billing_product_code, l1.total_cost timesheet_total_cost , l1.currency_id timesheet_total_cost_currency, IFNULL(l3.id,'') case_category_id , IFNULL(l3.name,'') category_name , IFNULL(l1.description,'') product_description , l5.intacct_id pro_intacct_id, l5.intacct_department intacct_dept_id, l2_cstm.macola_id_c account_macola_id_c, IFNULL(cases.id,'') primaryid , cases.case_number cases_case_number, l1.executed_time timesheet_executed_time FROM cases LEFT JOIN oss_timesheet l1 ON cases.id=l1.parent_id AND l1.deleted=0 AND l1.parent_type = 'Cases' LEFT JOIN accounts l2 ON cases.account_id=l2.id AND l2.deleted=0 LEFT JOIN oss_case_category_cases_1_c l3_1 ON cases.id=l3_1.oss_case_category_cases_1cases_idb AND l3_1.deleted=0 LEFT JOIN oss_case_category l3 ON l3.id=l3_1.oss_case_category_cases_1oss_case_category_ida AND l3.deleted=0 LEFT JOIN users l4 ON cases.assigned_user_id=l4.id AND l4.deleted=0 LEFT JOIN producttemplates_oss_timesheet_1_c l5_1 ON l1.id=l5_1.producttemplates_oss_timesheet_1oss_timesheet_idb AND l5_1.deleted=0 LEFT JOIN product_templates l5 ON l5.id=l5_1.producttemplates_oss_timesheet_1producttemplates_ida AND l5.deleted=0 LEFT JOIN product_categories pc ON pc.id = l5.category_id AND pc.deleted=0 LEFT JOIN accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c WHERE (((l1.total_cost > 0 ) AND ((l1.executed_time>='" . $startDate . "' AND l1.executed_time<='" . $endDate . "') ) AND (l3.name LIKE '%%strategic%%' ) AND ((coalesce(LENGTH(CONCAT(IFNULL(l4.first_name,''),' ',IFNULL(l4.last_name,''))), 0) <> 0)) AND (l5.name LIKE '%strategic%' or l5.name LIKE '%Board Governance%' or l5.name LIKE '%CEO Executive Coaching%'
 or l5.name LIKE '%Leadership & Employee Engagement%' or l5.name LIKE '%PanningPro Advisory Services%'
 or l5.name LIKE '%GovernEase Advisory Services%' ))) AND cases.deleted=0 ORDER BY account_name ASC";

        return self::getDataArray($sql);
    }



    public static function getHRPSSoldReport($startDate, $endDate, $account_type) {

        if($account_type == "Can-CUs" || $account_type == 'Can-Non-CUs'){
            $canadian_prod = " AND l1.name like 'CAD%'";
        }else{
            $canadian_prod = "";
        }

        $sql = "SELECT
            IFNULL(accounts.id, '') account_id,
            IFNULL(accounts.name, '') account_name,
            accounts_cstm.intacct_id acc_intacct_id,
            IFNULL(l1.id, '') product_id,
            IFNULL(l1.name, '') product_name,
            l1.date_support_expires,
            l1.date_purchased,
            l1.status product_status,
            l1.mft_part_num,
            IFNULL(l1.description, '') product_description,
            IF(l1.quantity = 0,1,l1.quantity) quantity,
            l1.discount_amount_usdollar,
            l1.discount_select,
            l1.list_price,
            l1_cstm.base_price_c base_price,
            l1.cost_price,
            l1.deal_calc,
            l1_cstm.hrps_secondary_sales_involvement_c,
            l1_cstm.prod_salesperson_c salesperson_c,
            IF(l1_cstm.sales_partner_c='blank','',l1_cstm.sales_partner_c) sales_partner,
            l2_cstm.comp_module_c comp_module,
            l2.compease_cid,
            l2.compensation_module,
            l2.emplic_count,
            l2.multi_appraiser multi_appraiser_type,
            l2.pro_type ce_system_type,
            l2.ma_lic_count,
            l2.cid pp_cid,
            l2.producturl_sp product_url,
            l2.renewable_product,
            l2.special_billing,
            l2.special_billing_instruction,
            product_templates.intacct_department intacct_dept_id,
            pc.name category_name,
            product_templates.intacct_id pro_intacct_id,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) intacct_emp_name,
            '".$account_type."' account_type
        FROM
            accounts
                INNER JOIN
            accounts_cstm ON accounts.id = accounts_cstm.id_c
                LEFT JOIN
            products l1 ON accounts.id = l1.account_id
                AND l1.deleted = 0
                INNER JOIN
            product_templates ON product_templates.id = l1.product_template_id AND product_templates.deleted = 0
                LEFT JOIN
            products_oss_hrn_1_c l2_1 ON l1.id = l2_1.products_oss_hrn_1products_ida
                AND l2_1.deleted = 0
                LEFT JOIN
            oss_hrn l2 ON l2.id = l2_1.products_oss_hrn_1oss_hrn_idb
                AND l2.deleted = 0
                LEFT JOIN
            products_cstm l1_cstm ON l1.id = l1_cstm.id_c
                LEFT JOIN
            product_categories pc ON pc.id = l1.category_id
                AND pc.deleted = 0
                LEFT JOIN
            oss_hrn_cstm l2_cstm ON l2.id = l2_cstm.id_c
                LEFT JOIN
            users ON users.user_name = l1_cstm.prod_salesperson_c AND users.deleted=0 AND users.user_name <> ''
        WHERE
            l1_cstm.product_status = 'hrn' AND
            (l1.name != 'CEO Performance Plan Engagement' AND l1.name != 'CEO Performance Plan Annual Update' AND l1.name != 'CEO Performance Plan Annual Completion'  AND l1.name != 'CEO Performance Review - STRAT') 
                AND l1.status = 'Sold'
                AND (l1.date_purchased >= '" . $startDate . "'
                AND l1.date_purchased <= '" . $endDate . "')
                AND accounts.deleted = 0".$canadian_prod;

        //return self::getDataArray($sql); accounts_cstm.acc_status_c='A' AND

        if ($account_type == "CUs"){
            $sql .= " AND accounts.account_type in ('ACM','NCM','OSC') ";

        }
        else if ($account_type == "Non-CUs") {

            $sql .= " AND accounts.account_type in ('OC','CUSO')";

        }else if($account_type == "Can-CUs"){
             $sql .= " AND accounts.account_type in ('ACM','NCM','OSC', 'CCU') ";
        }else if($account_type == "Can-Non-CUs"){
            $sql .= " AND accounts.account_type in ('OC') ";
        }
        //$sql .= " AND accounts.id='c8d8bb82-70d5-9eaf-25cd-5cb783983c8d' ";

        return self::getDataArray($sql);
    }



    public static function getHRPSRenewedReport($startDate, $endDate, $account_type) {

        if($account_type == "Can-CUs" || $account_type == 'Can-Non-CUs'){
            $canadian_prod = " AND l1.name like 'CAD%'";
        }else{
            $canadian_prod = "";
        }

        //3181 include gp A, gp B prices and qantities as well to all the renewal report.
        $can_fields = "
        l1_cstm.pre_2017_pricing_c pre_2017_pricing_c,
        l1_cstm.gp_a_license_count_c  as Group_A_License_Count,
        l1_cstm.gp_a_license_price_c  as Group_A_License_Price,

        l1_cstm.gp_a_license_count_c*l1_cstm.gp_a_license_price_c as Group_A_Total_Price,
        l1_cstm.gp_b_add_on_license_count_c  as 'Group_B_(add-on)_License_Count',
        l1_cstm.gp_b_add_on_license_price_c  as 'Group_B_(add-on)_License_Price',
        
        l1_cstm.gp_b_add_on_license_count_c*l1_cstm.gp_b_add_on_license_price_c as 'Group_B_(add-on)_Total_Price',
        CASE
WHEN l1_cstm.pre_2017_pricing_c = 0 AND l1.quantity > 0 THEN (l1_cstm.gp_a_license_count_c*l1_cstm.gp_a_license_price_c + l1_cstm.gp_b_add_on_license_price_c*l1_cstm.gp_b_add_on_license_count_c)/ l1.quantity ELSE 0 
END as 'avg_price',";

        echo $sql = "SELECT
            IFNULL(accounts.id, '') account_id,
            IFNULL(accounts.name, '') account_name,
            accounts_cstm.intacct_id acc_intacct_id,
            IFNULL(l1.id, '') product_id,
            IFNULL(l1.name, '') product_name,
            l1.date_support_expires,
            l1.date_purchased,
            l1.status product_status,
            l1.mft_part_num,
            IFNULL(l1.description, '') product_description,
            IF(l1.quantity = 0,1,l1.quantity) quantity,
            l1.list_price,
            l1.discount_amount_usdollar,
            l1.discount_select,
            l1_cstm.base_price_c base_price,".$can_fields."
            l1.cost_price,
            l1.deal_calc,
            l1_cstm.hrps_secondary_sales_involvement_c,
            l1_cstm.prod_salesperson_c salesperson_c,
            IF(l1_cstm.sales_partner_c='blank','',l1_cstm.sales_partner_c) sales_partner,
            l2_cstm.comp_module_c comp_module,
            l2.compease_cid,
            l2.compensation_module,
            l2.emplic_count,
            l2.multi_appraiser multi_appraiser_type,
            l2.pro_type ce_system_type,
            l2.ma_lic_count,
            l2.cid pp_cid,
            l2.producturl_sp product_url,
            l2.renewable_product,
            l2.special_billing,
            l2.special_billing_instruction,
            product_templates.intacct_department intacct_dept_id,
            pc.name case_category,
            product_templates.intacct_id pro_intacct_id,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) intacct_emp_name,
            '".$account_type."' account_type
        FROM
            accounts
                INNER JOIN
            accounts_cstm ON accounts_cstm.id_c = accounts.id
                LEFT JOIN
            products l1 ON accounts.id = l1.account_id
                AND l1.deleted = 0
                INNER JOIN
            product_templates ON product_templates.id = l1.product_template_id
                LEFT JOIN
            products_oss_hrn_1_c l2_1 ON l1.id = l2_1.products_oss_hrn_1products_ida
                AND l2_1.deleted = 0
                LEFT JOIN
            oss_hrn l2 ON l2.id = l2_1.products_oss_hrn_1oss_hrn_idb
                AND l2.deleted = 0
                LEFT JOIN
            products_cstm l1_cstm ON l1.id = l1_cstm.id_c
                LEFT JOIN
            product_categories pc ON pc.id = l1.category_id
                AND pc.deleted = 0
                LEFT JOIN
            oss_hrn_cstm l2_cstm ON l2.id = l2_cstm.id_c
                LEFT JOIN
            users ON users.user_name = l1_cstm.prod_salesperson_c AND users.deleted=0 AND users.user_name <> ''
        WHERE
            l1_cstm.product_status = 'hrn' AND
            (l1.name != 'CEO Performance Plan Engagement' AND l1.name != 'CEO Performance Plan Annual Update' AND l1.name != 'CEO Performance Plan Annual Completion'  AND l1.name != 'CEO Performance Review - STRAT') 
                AND l1.status = 'Renewed'
                AND (l1.date_support_expires >= " . "'$startDate '" .
            " AND l1.date_support_expires <= " . "'$endDate '" . ")
                AND accounts.deleted = 0".$canadian_prod;
        //return self::getDataArray($sql); AND accounts_cstm.acc_status_c='A'

        if ($account_type == "CUs"){
            $sql .= " AND accounts.account_type in ('ACM','NCM','OSC', 'League') ";

        }
        else if ($account_type == "Non-CUs") {

            $sql .= " AND accounts.account_type in ('OC','CUSO') ";

        }else if($account_type == "Can-CUs"){
             $sql .= " AND accounts.account_type in ('ACM','NCM','OSC', 'CCU', 'League') ";
        }else if($account_type == "Can-Non-CUs"){
            $sql .= " AND accounts.account_type in ('OC') ";
        }
    
        return self::getDataArray($sql);
    }

    public static function getChangesToLicensesReport($startDate, $endDate) {
        $sql = "SELECT
            IFNULL(products.id, '') products_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.status, '') products_status,
            products.description product_description,
            products.mft_part_num,
            l2.id account_id,
            l2_cstm.intacct_id acc_intacct_id,
            IFNULL(l2.name, '') account_name,
            l1_cstm.beginningcount_c beginningcount,
            l1_cstm.countchange_c quantity,
            l1_cstm.licensecost_c license_cost,
            l1_cstm.malicense_c ma_license,
            l1_cstm.newlicct_c new_lic_count,
            l1_cstm.type_c lic_type,
            l1_cstm.version_c cstm_version,
            l1.date_entered l1_date_entered,
            l1.date_modified l1_date_modified,
            product_templates.intacct_id pro_intacct_id,
            products_cstm.salesperson,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            product_templates.intacct_department intacct_dept_id,
            pc.name category_name,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) intacct_emp_name
        FROM
            products
                LEFT JOIN
            products_cstm ON products_cstm.id_c = products.id
                INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
                LEFT JOIN
            product_categories pc ON pc.id = products.category_id
                AND products.deleted = 0
                LEFT JOIN
            products_oss_pplicense_tracker_1_c l1_1 ON products.id = l1_1.products_oss_pplicense_tracker_1products_ida
                AND l1_1.deleted = 0
                LEFT JOIN
            oss_pplicense_tracker l1 ON l1.id = l1_1.products_oss_pplicense_tracker_1oss_pplicense_tracker_idb
                AND l1.deleted = 0
                LEFT JOIN
            accounts l2 ON products.account_id = l2.id
                AND l2.deleted = 0
                LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
                LEFT JOIN
            oss_pplicense_tracker_cstm l1_cstm ON l1.id = l1_cstm.id_c
                LEFT JOIN
            users ON users.user_name = products_cstm.salesperson AND users.deleted = 0 AND users.deleted=0 AND users.user_name <> ''
        WHERE
            (l1.date_modified >= '" . $startDate . "'
                AND l1.date_modified <= '" . $endDate . "')
                AND (products.status IN ('Sold' , 'Renewed'))
                AND ((products.name LIKE '%compease%')
                OR (products.name LIKE '%Performance%'))
                AND products.deleted = 0
                AND l1_cstm.countchange_c > 0";
        return self::getDataArray($sql);
    }

    public static function getComplianceConsultingBilledExpensesReport($startDate, $endDate) {
        $sql = "SELECT
            cases.case_number,
            cases.name sugar_subject,
            CONCAT(IFNULL(l4.first_name, ''),' ',IFNULL(l4.last_name, '')) sugar_support_rep,
            l4.intacct_emp_id,
            l5.mft_part_num,
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            IFNULL(l3.name, '') sugar_case_category,
            l1.billing_product_code,
            l1.total_cost,
            IFNULL(l1.description, '') timesheet_description,
            l5.intacct_id pro_intacct_id,
            l5.intacct_department intacct_dept_id,
            pc.name category_name
        FROM
            cases
                LEFT JOIN
            oss_timesheet l1 ON cases.id = l1.parent_id
                AND l1.deleted = 0
                AND l1.parent_type = 'Cases'
                LEFT JOIN
            accounts l2 ON cases.account_id = l2.id
                AND l2.deleted = 0
                LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
                LEFT JOIN
            oss_case_category_cases_1_c l3_1 ON cases.id = l3_1.oss_case_category_cases_1cases_idb
                AND l3_1.deleted = 0
                LEFT JOIN
            oss_case_category l3 ON l3.id = l3_1.oss_case_category_cases_1oss_case_category_ida
                AND l3.deleted = 0
                LEFT JOIN
            users l4 ON cases.assigned_user_id = l4.id
                AND l4.deleted = 0
                LEFT JOIN
            producttemplates_oss_timesheet_1_c l5_1 ON l1.id = l5_1.producttemplates_oss_timesheet_1oss_timesheet_idb
                AND l5_1.deleted = 0
                LEFT JOIN
            product_templates l5 ON l5.id = l5_1.producttemplates_oss_timesheet_1producttemplates_ida
                AND l5.deleted = 0
                LEFT JOIN
                product_categories pc ON pc.id = l5.category_id AND pc.deleted=0
        WHERE
            l1.total_cost > 0
                AND (l1.executed_time >= '" . $startDate . "'
                AND l1.executed_time <= '" . $endDate . "')
                AND l3.name LIKE '%%compliance%%'
                AND cases.assigned_user_id != ''
                AND l5.name LIKE '%%compliance%%'
                AND cases.deleted = 0
        ORDER BY account_name ASC";
        //print_r($sql);die;
        return self::getDataArray($sql);
    }

    public static function makeCSVReport($data, $fileName) {

        $fp = fopen($fileName, 'w');
        @ob_clean();
        $count = 0;
        if (!empty($data)) {
            foreach ($data as $row) {
                foreach ($row as $innerRow) {
                    if ($count != 1) {
                        $headerArray = array_keys($innerRow);
                        array_walk($headerArray, "self::headerReplace");
                        fputcsv($fp, $headerArray);
                        fputcsv($fp, $innerRow);
                        $count = 1;
                    } else {
                        fputcsv($fp, $innerRow);
                    }
                }
            }
        } else {
            $csvNoData = array('No data.');
            fputcsv($fp, $csvNoData);
        }
        fclose($fp);
        //exit(0);
    }

    public static function headerReplace(&$item, $key) {
        $item = str_replace('_', ' ', $item);
        $item = ucwords($item);
    }

    public static function getDateRange() {
        $todayDate = gmdate('d');
        $todayMonth = gmdate('m');

        //$todayDate = "23";
        //$todayMonth = "12";

        if ($todayDate == '7') {
            $startYearMonth = gmdate('Y-m', strtotime('-1 month', strtotime(gmdate('Y-m-d'))));
            $startDate = gmdate($startYearMonth . "-24");
            $startDate = gmdate('Y-m-d H:i:s', strtotime($startDate));

            $endYearMonth = gmdate('Y-m');
            $endDate = gmdate($endYearMonth . "-6");
            $endDate = gmdate('Y-m-d H:i:s', strtotime('+1 day - 1second', strtotime($endDate)));
        } elseif ($todayDate == '23') {
            $startYearMonth = gmdate('Y-m');
            $startDate = gmdate($startYearMonth . "-8");
            $startDate = gmdate('Y-m-d H:i:s', strtotime($startDate));

            $endDate = gmdate($startYearMonth . "-22");
            $endDate = gmdate('Y-m-d H:i:s', strtotime('+1 day - 1second', strtotime($endDate)));
        }
        if ($todayDate == '4') {
            $startYearMonth = gmdate('Y-m', strtotime('-1 month', strtotime(gmdate('Y-m-d'))));
            $startDate = gmdate($startYearMonth . "-21");
            $startDate = gmdate('Y-m-d H:i:s', strtotime($startDate));

            $endYearMonth = gmdate('Y-m');
            $endDate = gmdate($endYearMonth . "-3");
            $endDate = gmdate('Y-m-d H:i:s', strtotime('+1 day - 1second', strtotime($endDate)));
        } elseif ($todayDate == '20') {
            $startYearMonth = gmdate('Y-m');
            $startDate = gmdate($startYearMonth . "-5");
            $startDate = gmdate('Y-m-d H:i:s', strtotime($startDate));

            $endDate = gmdate($startYearMonth . "-19");
            $endDate = gmdate('Y-m-d H:i:s', strtotime('+1 day - 1second', strtotime($endDate)));
        }

        return array('start_date' => $startDate,
            'end_date' => $endDate);
    }

    public static function getAccountFromSugar($accountId) {
        global $db;

        $accSQL = "SELECT
                        a.id,
                        a.date_entered,
                        a.name,
                        ac.acc_charter_number_c,
                        ac.macola_id_c,
                        ac.acc_assets_c,
                        ac.acc_rnt_c,
                        ac.acc_members_c,
                        ac.acc_charter_number_c,
                        ac.county_c,
                        a.phone_office,
                        a.phone_fax,
                        ac.taxable_c,
                        a.billing_address_street,
                        a.billing_address_city,
                        a.billing_address_state,
                        a.billing_address_postalcode,
                        a.billing_address_country,
                        a.shipping_address_street,
                        a.shipping_address_city,
                        a.shipping_address_state,
                        a.shipping_address_postalcode,
                        a.shipping_address_country
                    FROM
                        accounts a
                            INNER JOIN
                        accounts_cstm ac ON ac.id_c = a.id
                    WHERE a.deleted = 0
                    AND a.id = '" . $accountId . "'";
        $accQuery = $db->query($accSQL);
        $accResult = $db->fetchByAssoc($accQuery);
        return $accResult;
    }

    public static function updateIntacctIdToAccount($intacctId, $sugarId) {
        global $db;
        $sql = "UPDATE accounts_cstm SET intacct_id='" . $intacctId . "' WHERE id_c='" . $sugarId . "'";
        $db->query($sql);
    }

    

    public static function getMemberXPBillingReport($startDate, $endDate, $productIds='', $withoutfreq=1) {
        $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
            products.date_purchased date_purchased,
            products.date_support_expires date_support_expires,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
            products.discount_amount,products.cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id,addr.email_address, products_cstm.billing_frequency_c,
            '".$withoutfreq."' wihoutfeq
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
            LEFT JOIN
            email_addr_bean_rel rel ON products.contact_id = rel.bean_id AND rel.primary_address = 1 AND rel.deleted = 0
            LEFT JOIN
            email_addresses addr ON addr.id = rel.email_address_id AND addr.deleted = 0
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            ((
            (products.status = 'Renewed' || products.status = 'Sold')
            AND (l1.name LIKE 'CUSG - Marketing Solutions' AND products.name like '%MemberXP%')
            AND (products.cost_price > 0)))
            AND products.deleted = 0 ";
// and products.id ='84151b5a-73b4-11ea-b665-0294cb7f6c72'
            if($withoutfreq == 1){
                $sql .= " AND (products.date_support_expires >= " . "'$startDate'" .
                        " AND products.date_support_expires <= " . "'$endDate'" . " )
                        And products.id NOT IN (".$productIds.") AND products_cstm.billing_frequency_c IS NULL  ";
            }else{
                $sql .= " And products.id IN (".$productIds.") AND products_cstm.billing_frequency_c  IS NOT NULL  ";
            }

            $sql .= " ORDER BY account_name ASC";

        return self::getDataArray($sql);
    }

    public static function getBillingFrequencyReport($productId,$templateId) {
        $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
            products.date_support_expires date_support_expires,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
			products.discount_amount,products.cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            products.id IN (".$productId.") AND
            product_templates.id IN (".$templateId.")";

        return self::getDataArray($sql);
    }

    public static function getBillingFrequencyCsv($templateId,$startDate, $endDate) {
        $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
            products.date_support_expires date_support_expires,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
			products.discount_amount,mculi_billingsfrequency_cstm.amount_c cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id,
            mculi_billingsfrequency_cstm.scheduled_date_c,
            addr.email_address
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
			INNER JOIN
            products_mculi_billingsfrequency_1_c ON products.id = products_mculi_billingsfrequency_1products_ida
            INNER JOIN
            mculi_billingsfrequency_cstm ON products_mculi_billingsfrequency_1mculi_billingsfrequency_idb = mculi_billingsfrequency_cstm.id_c
            LEFT JOIN
            email_addr_bean_rel rel ON products.contact_id = rel.bean_id
            LEFT JOIN
            email_addresses addr ON addr.id = rel.email_address_id
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            (products.status = 'Renewed' || products.status = 'Sold') AND
            product_templates.id IN (".$templateId.") AND (mculi_billingsfrequency_cstm.scheduled_date_c >= " . "'$startDate'" .
            " AND mculi_billingsfrequency_cstm.scheduled_date_c <= " . "'$endDate'" . " )  AND products_mculi_billingsfrequency_1_c.deleted = 0 AND rel.primary_address = 1 AND rel.deleted=0";

            
        return self::getDataArray($sql);
    }

    public static function getLeagueInfoSightMonthlyBillingReport($startDate, $endDate) {
        $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
            products.date_support_expires date_support_expires,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
			products.discount_amount,products.cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            (((products.date_support_expires >= " . "'$startDate'" .
            " AND products.date_support_expires <= " . "'$endDate'" . " )
            AND (products.status = 'Renewed')
            AND (l1.name LIKE '%League InfoSight%')
            AND (products.cost_price > 0)
            AND (products.name LIKE '%RecoveryPro - Online%')))
            AND products.deleted = 0 ORDER BY account_name ASC";


        return self::getDataArray($sql);
    }

      public static function getLISSoldMonthlyBillingReport($startDate, $endDate) {
        echo $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
             products.date_support_expires date_support_expires,
            products.date_support_starts date_support_starts,
            l2.billing_address_state billing_address_state,
            l2_cstm.acc_assets_c assets,
            l2_cstm.acc_charter_number_c charter_number,
            l2_cstm.acc_ceo_bio_c affiliation_status,
            products.status product_status,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
            products.discount_amount,products.cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            (products.date_purchased >= " . "'$startDate'" .
            " AND products.date_purchased <= " . "'$endDate'" . " )
            
            AND (l1.name LIKE '%League InfoSight%')
            AND (products.cost_price > 0)
            AND (products.name LIKE '%CU PolicyPro – League%' 
|| products.name LIKE '%CU PolicyPro – Web%' 
|| products.name LIKE '%CU PolicyPro – Web New Sale%'
|| products.name LIKE '%InfoSight - League Subscription%'
|| products.name LIKE '%RecoveryPro - Online%') 
            AND products.status = 'Sold'
            AND products.deleted = 0 ORDER BY account_name ASC";


        return self::getDataArray($sql);
    }

    public static function getCUPPRenewaldMonthlyBillingReport($startDate, $endDate) {
        echo $sql = "SELECT
            IFNULL(l2.id, '') account_id,
            IFNULL(l2.name, '') account_name,
            l2_cstm.intacct_id acc_intacct_id,
            product_templates.intacct_id pro_intacct_id,
            IFNULL(products.id, '') product_id,
            IFNULL(products.name, '') products_name,
            IFNULL(products.mft_part_num, '') mft_part_num,
            IFNULL(products.description, '') products_description,
            products.date_support_expires date_support_expires,
           products.date_support_starts date_support_starts,
            l2.billing_address_state billing_address_state,
            l2_cstm.acc_assets_c assets,
            l2_cstm.acc_charter_number_c charter_number,
            l2_cstm.acc_ceo_bio_c affiliation_status,
            products.status product_status,
            IF(products.quantity = 0,1,products.quantity) quantity,
            products.list_price products_cost_price,
            products.discount_amount,products.cost_price,
            IFNULL(products_cstm.prod_salesperson_c, '') salesperson_username,
            IF(products_cstm.sales_partner_c='blank','',products_cstm.sales_partner_c) sales_partner,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) salesperson,
            product_templates.intacct_department intacct_dept_id,
            l1.name category_name,
            users.intacct_emp_id intacct_emp_id
            FROM
            products
            INNER JOIN
            product_templates ON product_templates.id = products.product_template_id
            LEFT JOIN
            product_categories l1 ON products.category_id = l1.id
            AND l1.deleted = 0
            LEFT JOIN
            accounts l2 ON products.account_id = l2.id
            AND l2.deleted = 0
            LEFT JOIN
            accounts_cstm l2_cstm ON l2.id = l2_cstm.id_c
            LEFT JOIN
            products_cstm products_cstm ON products.id = products_cstm.id_c
            LEFT JOIN
            users ON users.user_name = products_cstm.prod_salesperson_c AND users.deleted=0
            WHERE
            (products.date_support_expires >= " . "'$startDate'" .
            " AND products.date_support_expires <= " . "'$endDate'" . " )
            
            AND (l1.name LIKE '%League InfoSight%')
            AND (products.cost_price > 0)
            AND products.status = 'Renewed' 
            AND (   products.name LIKE '%CU PolicyPro – League%' OR 
                    products.name LIKE '%CU PolicyPro – Web%' OR 
                    products.name LIKE '%CU PolicyPro – Web New Sale%' OR 
                    products.name LIKE '%InfoSight - League Subscription%'
                ) 

            AND products.deleted = 0 ORDER BY account_name ASC";


        return self::getDataArray($sql);
    }

    public static function getSASSoldReport($startDate, $endDate) {

        $sql = "SELECT
            IFNULL(accounts.id, '') account_id,
            IFNULL(accounts.name, '') account_name,
            accounts_cstm.intacct_id acc_intacct_id,
            IFNULL(l1.id, '') product_id,
            IFNULL(l1.name, '') product_name,
            l1.date_support_expires,
            l1.date_purchased,
            l1.status product_status,
            l1.mft_part_num,
            IFNULL(l1.description, '') product_description,
            IF(l1.quantity = 0,1,l1.quantity) quantity,
            l1.discount_amount_usdollar,
            l1.discount_select,
            l1.list_price,
            l1_cstm.base_price_c base_price,
            l1.cost_price,
            l1.deal_calc,
            l1_cstm.hrps_secondary_sales_involvement_c,
            l1_cstm.prod_salesperson_c salesperson_c,
            IF(l1_cstm.sales_partner_c='blank','',l1_cstm.sales_partner_c) sales_partner,
            l2_cstm.comp_module_c comp_module,
            l2.compease_cid,
            l2.compensation_module,
            l2.emplic_count,
            l2.multi_appraiser multi_appraiser_type,
            l2.pro_type ce_system_type,
            l2.ma_lic_count,
            l2.cid pp_cid,
            l2.producturl_sp product_url,
            l2.renewable_product,
            l2.special_billing,
            l2.special_billing_instruction,
            product_templates.intacct_department intacct_dept_id,
            pc.name category_name,
            product_templates.intacct_id pro_intacct_id,
            users.intacct_emp_id intacct_emp_id,
            LTRIM(RTRIM(CONCAT(IFNULL(users.first_name, ''),' ',IFNULL(users.last_name, '')))) intacct_emp_name,
            '".$account_type."' account_type
        FROM
            accounts
                INNER JOIN
            accounts_cstm ON accounts.id = accounts_cstm.id_c
                LEFT JOIN
            products l1 ON accounts.id = l1.account_id
                AND l1.deleted = 0
                INNER JOIN
            product_templates ON product_templates.id = l1.product_template_id AND product_templates.deleted = 0
                LEFT JOIN
            products_oss_hrn_1_c l2_1 ON l1.id = l2_1.products_oss_hrn_1products_ida
                AND l2_1.deleted = 0
                LEFT JOIN
            oss_hrn l2 ON l2.id = l2_1.products_oss_hrn_1oss_hrn_idb
                AND l2.deleted = 0
                LEFT JOIN
            products_cstm l1_cstm ON l1.id = l1_cstm.id_c
                LEFT JOIN
            product_categories pc ON pc.id = l1.category_id
                AND pc.deleted = 0
                LEFT JOIN
            oss_hrn_cstm l2_cstm ON l2.id = l2_cstm.id_c
                LEFT JOIN
            users ON users.user_name = l1_cstm.prod_salesperson_c AND users.deleted=0 AND users.user_name <> ''
        WHERE
            (l1.name = 'CEO Performance Plan Engagement'|| l1.name = 'CEO Performance Plan Annual Update' || l1.name = 'CEO Performance Plan Annual Completion' || l1.name = 'CEO Performance Review - STRAT') 
                AND (l1.date_purchased >= '" . $startDate . "'
                AND l1.date_purchased <= '" . $endDate . "')
                AND accounts.deleted = 0";


        return self::getDataArray($sql);
    }


}