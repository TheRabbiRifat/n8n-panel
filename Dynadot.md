# Dynadot API Documentation

## Getting Started

**API URL:** `https://api.dynadot.com/api3.xml` (XML) or `https://api.dynadot.com/api3.json` (JSON)

**Secure URL:** Requests should be sent over https (secure socket).

**Required Parameters:**
* `Key`: Your API key (found in Tools -> API).
* `Command`: The command you wish to execute.

**Rate Limits:**
* **Regular:** 1 thread, 60/min (1/sec)
* **Bulk:** 5 threads, 600/min (10/sec)
* **Super Bulk:** 25 threads, 6000/min (100/sec)
* **Premium Bulk:** 25 threads, 6000/min (100/sec)

---

## Commands

### Search
Searches for domain availability.

**Parameters:**
* `domain0` - `domain99`: The domain name(s) you are searching for.
* `language0` - `language99` (optional): The language tag for IDNs.
* `show_price` (optional): Set to "1" to show price.
* `currency` (optional): "USD", "CNY", "GBP", "EUR", "INR", "CAD", etc.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=search&domain0=mydomain.com&domain1=mydomain.net&show_price=1&currency=USD`

**Example Response (JSON):**
```json
{
   "SearchResponse":{
      "ResponseCode":"0",
      "SearchResults":[
         {
            "DomainName":"mydomain.com",
            "Available":"yes",
            "Price":"77.00 in USD"
         },
         {
            "DomainName":"mydomain.net",
            "Available":"yes",
            "Price":"44.00 in USD"
         }
      ]
   }
}
```

### Register
Creates and processes a registration order.

**Parameters:**
* `domain`: The domain name to register.
* `duration`: Years to register.
* `currency` (optional): Currency type.
* `registrant_contact` (optional): Contact ID.
* `admin_contact` (optional): Contact ID.
* `technical_contact` (optional): Contact ID.
* `billing_contact` (optional): Contact ID.
* `premium` (optional): Set to "1" for premium domains.
* `coupon` (optional): Coupon code.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=register&domain=domain1.net&duration=3&currency=USD`

**Example Response (JSON):**
```json
{
  "RegisterResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "DomainName": "domain1.net",
    "Expiration": 1458379145266
  }
}
```

### Delete
Deletes a domain that is still in the grace period.

**Parameters:**
* `domain`: The domain name to delete.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=delete&domain=domain1.com`

**Example Response (JSON):**
```json
{
  "DeleteResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "DomainName": "domain1.com"
  }
}
```

### Renew
Creates and processes a renewal order.

**Parameters:**
* `domain`: The domain name to renew.
* `duration`: Years to renew.
* `year` (optional): Current expiry year.
* `currency` (optional): Currency type.
* `price_check` (optional): Add to check price only.
* `coupon` (optional): Coupon code.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&domain=domian1.com&command=renew&duration=1&currency=USD`

**Example Response (JSON):**
```json
{
   "RenewResponse":{
      "ResponseCode":"0",
      "Status":"success",
      "DomainName":"domain1.com",
      "Expiration":"73984579834"
   }
}
```

### Push
Initiates a domain push to another Dynadot account.

**Parameters:**
* `domain`: Domain(s) to push (semicolon separated for bulk).
* `receiver_push_username`: Recipient's push username.
* `currency` (optional): Currency.
* `unlock_domain_for_push` (optional): "1" to auto-unlock.
* `receiver_email` (optional): Recipient email.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=push&domain=domian1.com&receiver_push_username=username`

**Example Response (JSON):**
```json
{
   "PushResponse":{
      "ResponseCode":"0",
      "Status":"order created"
   }
}
```

### Transfer
Initiates a domain transfer to Dynadot.

**Parameters:**
* `domain`: Domain to transfer.
* `auth`: Authorization code.
* `currency` (optional): Currency.
* `registrant_contact` (optional): Contact ID.
* `admin_contact` (optional): Contact ID.
* `technical_contact` (optional): Contact ID.
* `billing_contact` (optional): Contact ID.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=transfer&domain=domian1.com&auth=testauth`

**Example Response (JSON):**
```json
{
  "TransferResponse": {
    "ResponseCode": 0,
    "Status": "order created",
    "DomainName": "domain1.com",
    "OrderId": "1234567"
  }
}
```

### Bulk Register
Registers multiple domains.

**Parameters:**
* `domain0`-`domain99`: Domains to register.
* `currency` (optional): Currency.
* `premium` (optional): "1" for premium.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=bulk_register&domain0=domain0.com&domain1=domain1.com`

**Example Response (JSON):**
```json
{
  "BulkRegisterResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "BulkRegister": [
      {
        "DomainName": "domain0.com",
        "Result": "success",
        "Message": "-"
      },
      {
        "DomainName": "domain1.com",
        "Result": "error",
        "Message": "not_available"
      }
    ]
  }
}
```

### Restore
Restores a domain from redemption period.

**Parameters:**
* `domain`: The domain name to restore.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=restore&domain=domain1.com`

**Example Response (JSON):**
```json
{
  "RestoreResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "DomainName": "domain1.com"
  }
}
```

### Domain Info
Gets information about a domain.

**Parameters:**
* `domain`: The domain name.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=domain_info&domain=domain1.com`

**Example Response (JSON):**
```json
{
  "DomainInfoResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "DomainInfo": {
      "Name": "domain1.com",
      "Expiration": "1361430589062",
      "Registration": "1234567890123",
      "NameServerSettings": {
        "Type": "Dynadot Parking",
        "WithAds": "Yes"
      },
      "Locked": "yes",
      "Status": "active"
    }
  }
}
```

### Set Whois
Sets contact information for a domain.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `registrant_contact`: Contact ID.
* `admin_contact`: Contact ID.
* `technical_contact`: Contact ID.
* `billing_contact`: Contact ID.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_whois&domain=domain4.com&registrant_contact=0&admin_contact=0`

**Example Response (JSON):**
```json
{
   "SetWhoisResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Name Servers
Sets name servers for a domain.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `ns0` - `ns12`: Name servers.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_ns&domain=domain1.com&ns0=ns1.hostns.com&ns1=ns2.hostns.com`

**Example Response (JSON):**
```json
{
  "SetNsResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Set Parking
Sets domain to parking.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `with_ads` (optional): "no" to disable ads.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_parking&domain=domain1.com&with_ads=no`

**Example Response (JSON):**
```json
{
   "SetParkingResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Forwarding
Sets domain forwarding.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `forward_url`: Destination URL (encoded).
* `is_temp` (optional): "no" for permanent (301).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_forwarding&forward_url=http://www.mydomain.com&domain=domain1.com`

**Example Response (JSON):**
```json
{
   "SetForwardingResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Stealth
Sets stealth forwarding.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `stealth_url`: URL (encoded).
* `stealth_title` (optional): Page title.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_stealth&domain=domain1.com&stealth_url=http://www.obamashop.com`

**Example Response (JSON):**
```json
{
   "SetStealthResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Hosting
Sets domain hosting.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `hosting_type`: "basic" or "advanced".
* `mobile_view_on` (optional): "yes" (advanced only).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_hosting&domain=domain8.com&hosting_type=advanced`

**Example Response (JSON):**
```json
{
   "SetHostingResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set DNS2
Sets DNS records.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `main_record_type0`...: "a", "aaaa", "cname", "txt", "mx", etc.
* `main_record0`...: Value.
* `subdomain0`...: Subdomain.
* `add_dns_to_current_setting` (optional): "1" to append.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_dns2&domain=domain1.com&main_record_type0=aaaa&main_record0=0:0:0:0:0:0:0:1`

**Example Response (JSON):**
```json
{
  "SetDnsResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Set DNSSEC
Sets DNSSEC records.

**Parameters:**
* `domain_name`: Domain name.
* `key_tag`, `digest_type`, `digest`, `algorithm` OR `flags`, `public_key`, `algorithm`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_dnssec&domain_name=domain-haha1.com&flags=257`

**Example Response (JSON):**
```json
{
  "SetDnssecResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Set Email Forward
Sets email forwarding.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `forward_type`: "donot", "mx", "forward".
* `username0`...: User part (for "forward").
* `exist_email0`...: Destination email.
* `mx_host0`...: Mail host (for "mx").

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_email_forward&domain=testdomain.com&forward_type=forward&username0=test`

**Example Response (JSON):**
```json
{
   "SetEmailForwardingResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Clear Domain Setting
Clears specific domain settings.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `service`: "forward", "stealth", "email_forwarding", "dns", "dnssec", "nameservers".

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_clear_domain_setting&domain=domain1.com&service=nameservers`

**Example Response (JSON):**
```json
{
   "SetClearDomainSettingResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Folder
Moves domain to a folder.

**Parameters:**
* `domain`: Domain name.
* `folder`: Folder name (case sensitive).
* `folder_id` (optional): Folder ID.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_folder&domain=domian1.com&folder=folder1`

**Example Response (JSON):**
```json
{
   "SetFolderResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Note
Sets a note for a domain.

**Parameters:**
* `domain`: Domain name.
* `note`: The note content.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_note&domain=domain1.com&note=Do_not_modify`

**Example Response (JSON):**
```json
{
   "SetNoteResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Customer ID (Reseller)
Sets customer ID for a domain.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `customer_id`: Customer ID.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_customer_id&domain=domain1.com&customer_id=123456`

**Example Response (JSON):**
```json
{
  "SetCustomerIdResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "SetCustomerIdSuccess": [
      {
        "Domain": "domain1.com",
        "CustomerId": 123456
      }
    ]
  }
}
```

### Set Renew Option
Sets renewal preference.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `renew_option`: "donot", "auto", "reset".

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_renew_option&domain=domain1.com&renew_option=auto`

**Example Response (JSON):**
```json
{
  "SetRenewOptionResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Set Privacy
Sets privacy options.

**Parameters:**
* `domain`: Domain(s), comma separated.
* `option`: "full", "partial", "off".
* `whois_privacy_option`: "yes", "no".

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_privacy&domain=domain1.com&whois_privacy_option=yes&option=off`

**Example Response (JSON):**
```json
{
   "SetPrivacyResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### TLD Prices
Gets pricing for TLDs.

**Parameters:**
* `currency` (optional).
* `count_per_page`, `page_index`, `sort` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=tld_price&currency=USD`

**Example Response (JSON):**
```json
{
  "TldPriceResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "TldPrice": [
      {
        "Tld": "com",
        "Price": {
          "Register": "15.00",
          "Renew": "15.00"
        }
      }
    ]
  }
}
```

### List Domain
Lists domains in the account.

**Parameters:**
* `count_per_page`, `page_index`, `sort` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=list_domain`

**Example Response (JSON):**
```json
{
  "ListDomainInfoResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "MainDomains": [
      {
        "Name": "domain-exp140.com",
        "Expiration": "1361430589062",
        "Status": "active"
      }
    ]
  }
}
```

### Lock Domain
Locks a domain.

**Parameters:**
* `domain`: Domain name.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=lock_domain&domain=domain4.com`

**Example Response (JSON):**
```json
{
   "LockDomainResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Cancel Transfer
Cancels a pending transfer.

**Parameters:**
* `domain`: Domain name.
* `order_id`: From `get_transfer_status`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=cancel_transfer&domain=domain4.com`

**Example Response (JSON):**
```json
{
  "CancelTransferResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Get Transfer Status
Gets status of domain transfers.

**Parameters:**
* `domain`: Domain name.
* `transfer_type`: "in" or "away".

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_transfer_status&domain=domian1.com&transfer_type=in`

**Example Response (JSON):**
```json
{
  "GetTransferStatusResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "TransferList": [
      {
        "OrderId": "testorderid",
        "TransferStatus": "teststatus"
      }
    ]
  }
}
```

### Set Transfer Auth Code
Updates auth code for transfer.

**Parameters:**
* `domain`: Domain name.
* `auth_code`: New code.
* `order_id`: Transfer order ID.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_transfer_auth_code&domain=domain4.com&auth_code=test`

**Example Response (JSON):**
```json
{
   "SetTransferAuthCodeResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Authorize Transfer Away
Approves or denies a transfer away.

**Parameters:**
* `domain`: Domain name.
* `authorize`: "approve" or "deny".
* `order_id`: Order ID.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=authorize_transfer_away&domain=domain.com&authorize=approve&order_id=123`

**Example Response (JSON):**
```json
{
  "AuthorizeTransferAwayResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "Result": "away_approved"
  }
}
```

### Get Transfer Auth Code
Gets or generates transfer auth code.

**Parameters:**
* `domain`: Domain name.
* `new_code` (optional): "1" to generate new.
* `unlock_domain_for_transfer` (optional): "1" to unlock.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_transfer_auth_code&domain=domian1.com`

**Example Response (JSON):**
```json
{
  "GetTransferAuthCodeResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "AuthCode": "testauthcode"
  }
}
```

### Get Domain Push Request
Gets pending incoming push requests.

**Parameters:** None.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_domain_push_request`

**Example Response (JSON):**
```json
{
  "GetDomainPushRequestResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "pushDomainName": "[haha.com,haha1.com]"
  }
}
```

### Set Domain Push Request
Accepts or declines a push request.

**Parameters:**
* `domains`: Domain(s).
* `action`: "accept" or "decline".

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_domain_push_request&domains=domain-haha1.com&action=accept`

**Example Response (JSON):**
```json
{
  "SetDomainPushRequestResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Create CN Audit
Creates .CN domain audit.

**Parameters:**
* `contact_id`, `contact_type` ("Individual" or "Enterprise").
* `individual_id_type`... / `enterprise_id_type`...

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=create_cn_audit&contact_id=test&contact_type=Enterprise`

**Example Response (JSON):**
```json
{
  "CreateCnAuditResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Get CN Audit Status
Checks status of CN audit.

**Parameters:**
* `contact_id`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_cn_audit_status&contact_id=testcontactid`

**Example Response (JSON):**
```json
{
  "GetCnAuditStatusResponse": {
    "ResponseCode": "0",
    "Status": "success",
    "CnAuditStatus": "pass"
  }
}
```

### Create Contact
Creates a new contact.

**Parameters:**
* `name`, `email`, `phonenum`, `phonecc`, `address1`, `city`, `zip`, `country`.
* Optional: `organization`, `state`, `address2`, etc.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=create_contact&name=Webb&email=test@example.com&phonenum=123456&phonecc=1&address1=PO&city=SanMateo&zip=94401&country=US`

**Example Response (JSON):**
```json
{
   "CreateContactResponse":{
      "ResponseCode":"0",
      "Status":"success",
      "CreateContactContent":{
         "ContactId":"123"
      }
   }
}
```

### Edit Contact
Edits an existing contact.

**Parameters:**
* `contact_id`: ID of contact to edit.
* Fields to update (`name`, `email`, etc).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=edit_contact&contact_id=0&name=Webb`

**Example Response (JSON):**
```json
{
  "EditContactResponse": {
    "ResponseCode": "0",
    "Status": "success",
    "EditContactContent": {
      "ContactId": "0"
    }
  }
}
```

### Delete Contact
Deletes a contact.

**Parameters:**
* `contact_id`: ID(s) comma separated.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=delete_contact&contact_id=0`

**Example Response (JSON):**
```json
{
  "DeleteContactResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Contact List
Lists all contacts.

**Parameters:** None.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=contact_list`

**Example Response (JSON):**
```json
{
  "ContactListResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "ContactList": [
      {
        "ContactId": "0",
        "Name": "Jack tester"
      }
    ]
  }
}
```

### Get Contact
Gets details of a specific contact.

**Parameters:**
* `contact_id`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_contact&contact_id=10000`

**Example Response (JSON):**
```json
{
  "GetContactResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "GetContact": {
      "ContactId": "12345",
      "Name": "name"
    }
  }
}
```

### Set Contact EU/LV/LT Setting
Sets specific settings for EU, Latvia, or Lithuania contacts.

**Commands:** `set_contact_eu_setting`, `set_contact_lv_setting`, `set_contact_lt_setting`.
**Parameters:** `contact_id` and specific fields (e.g., `country_of_citizenship`, `registration_number`).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_contact_eu_setting&contact_id=0&country_of_citizenship=AT`

**Example Response (JSON):**
```json
{
  "SetContactEUSettingResponse": {
    "ResponseCode: ": "0",
    "Status": "Success"
  }
}
```

### Get Name Server
Gets name server IP info.

**Parameters:**
* `domain`: The domain (host).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_ns&domain=mydomain.com`

**Example Response (JSON):**
```json
{
  "GetNsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "NsContent": {
      "Host0": "ns1.mydomain.com",
      "Host1": "ns2.mydomain.com"
    }
  }
}
```

### Add Name Server
Adds a registered name server.

**Parameters:**
* `host`: Hostname.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=add_ns&host=ns1.mydomain.com`

**Example Response (JSON):**
```json
{
  "AddNsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "AddNsContent": {
      "Server": {
        "Host": "ns1.mydomain.com",
        "ServerId": 0
      }
    }
  }
}
```

### Register Name Server
Registers a new name server with IP.

**Parameters:**
* `host`: Hostname.
* `ip`: IP Address.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=register_ns&host=domain1.com&ip=192.168.1.1`

**Example Response (JSON):**
```json
{
   "RegisterNsResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Set Name Server IP
Updates name server IP.

**Parameters:**
* `server_id`: Server ID.
* `ip0`...: New IP(s).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_ns_ip&server_id=1&ip0=192.168.1.1`

**Example Response (JSON):**
```json
{
   "SetNsIpResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Delete Name Server
Deletes a name server (by ID or Domain).

**Commands:** `delete_ns`, `delete_ns_by_domain`.
**Parameters:** `server_id` or `server_domain`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=delete_ns&server_id=0`

**Example Response (JSON):**
```json
{
  "DeleteNsResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Server List
Lists registered name servers.

**Parameters:** None.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=server_list`

**Example Response (JSON):**
```json
{
  "ServerListResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "ServerList": [
      {
        "ServerId": "0",
        "ServerName": "ns1.com"
      }
    ]
  }
}
```

### Get Domain DNS Settings
Gets DNS settings for a domain.

**Parameters:**
* `domain`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_dns&domain=mydomain.com`

**Example Response (JSON):**
```json
{
  "GetDnsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "GetDns": {
      "NameServerSettings": {
        "Type": "Dynadot Parking"
      }
    }
  }
}
```

### Account Info
Gets account details.

**Parameters:** None.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=account_info`

**Example Response (JSON):**
```json
{
  "AccountInfoResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "AccountInfo": {
      "Username": "testname",
      "AccountBalance": "$70.02"
    }
  }
}
```

### Get Account Balance
Gets balance.

**Parameters:** None.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_account_balance`

**Example Response (JSON):**
```json
{
  "GetAccountBalanceResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "BalanceList": [
      {
        "Currency": "USD",
        "Amount": "300.00"
      }
    ]
  }
}
```

### Set Default Settings
Sets default account settings.

**Commands:**
* `set_default_whois`
* `set_default_ns`
* `set_default_parking`
* `set_default_forwarding`
* `set_default_stealth`
* `set_default_hosting`
* `set_default_dns2`
* `set_default_email_forward`
* `set_default_renew_option`

**Parameters:** Vary by command (similar to domain-specific set commands).

**Example Request (Default NS):**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_default_ns&ns0=ns1.hosts.com`

**Example Response (JSON):**
```json
{
  "SetDefaultNsResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Clear Default/Folder Settings
Clears default or folder settings.

**Commands:** `set_clear_default_setting`, `set_clear_folder_setting`.
**Parameters:** `service` (and `folder_id` for folder command).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_clear_default_setting&service=nameservers`

**Example Response (JSON):**
```json
{
   "SetClearDefaultSettingResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Folder Management
Manages folders.

**Commands:**
* `create_folder` (param: `folder_name`)
* `delete_folder` (param: `folder_id`)
* `set_folder_name` (param: `folder_id`, `folder_name`)
* `folder_list` (no params)

**Example Request (Create):**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=create_folder&folder_name=new`

**Example Response (JSON):**
```json
{
   "FolderCreateResponse":{
      "ResponseCode":"0",
      "Status":"success",
      "FolderCreateContent":{
         "FolderName":"new",
         "FolderId":"1"
      }
   }
}
```

### Folder Settings
Sets settings for domains within a folder (and future domains).

**Commands:**
* `set_folder_whois`
* `set_folder_ns`
* `set_folder_parking`
* `set_folder_forwarding`
* `set_folder_stealth`
* `set_folder_hosting`
* `set_folder_dns2`
* `set_folder_email_forward`
* `set_folder_renew_option`

**Parameters:** `folder_id` plus command specific settings. `enable=yes` applies to future domains, `sync=yes` applies to existing.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_folder_ns&folder_id=0&ns0=ns1.hosts.com&sync=yes`

**Example Response (JSON):**
```json
{
  "SetFolderNsResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Add Backorder Request
Requests to backorder a domain.

**Parameters:**
* `domain`: The domain(s) to backorder (comma separated).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=add_backorder_request&domain=droppingdomain.com`

**Example Response (JSON):**
```json
{
   "AddBackorderRequestResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Delete Backorder Request
Removes a backorder request.

**Parameters:**
* `domain`: The domain(s) to remove (comma separated).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=delete_backorder_request&domain=domaindropp.com`

**Example Response (JSON):**
```json
{
   "DeleteBackorderRequestResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Backorder Request List
Lists current backorder requests.

**Parameters:**
* `startDate`: Start date (yyyy-mm-dd).
* `endDate`: End date (yyyy-mm-dd).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=backorder_request_list&startDate=2015-01-01&endDate=2015-5-20`

**Example Response (JSON):**
```json
{
  "BackorderRequestListResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "BackorderRequestList": [
      {
        "DomainName": "testdrop.com",
        "BackorderRequestStatus": "Active"
      }
    ]
  }
}
```

### Get Open Auctions
Lists auctions in progress.

**Parameters:**
* `currency` (optional).
* `type` (optional): "expired", "user", "backorder", etc.
* `count_per_page`, `page_index`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_open_auctions&currency=usd&count_per_page=20`

**Example Response (JSON):**
```json
{
    "status": "success",
    "auction_list": [
        {
            "auction_id": 11,
            "domain": "domain.com",
            "current_bid_price": "124.00"
        }
    ]
}
```

### Get Auction Details
Gets details for specific auction(s).

**Parameters:**
* `domain`: Domain name.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_auction_details&domain=domain0.com&currency=usd`

**Example Response (JSON):**
```json
{
  "status": "success",
  "auction_details": [
    {
      "auction_json": {
        "auction_id": 0,
        "domain": "test.biz",
        "current_bid_price": "46.99"
      }
    }
  ]
}
```

### Get Auction Bids
Gets your bid list.

**Parameters:**
* `currency` (optional).
* `count_per_page`, `page_index`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_auction_bids&currency=usd&count_per_page=20`

**Example Response (JSON):**
```json
{
    "status": "success",
    "auction_bids": [
        {
            "bid_id": 0,
            "auction_id": 11,
            "domain": "domain.com",
            "your_status": "High Bidder"
        }
    ]
}
```

### Place Auction Bid
Places a bid on an auction.

**Parameters:**
* `domain`: Domain name.
* `bid_amount`: Bid amount.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=place_auction_bid&domain=example.com&bid_amount=99.9&currency=usd`

**Example Response (JSON):**
```json
{
    "status": "success",
    "auction_details": {
        "auction_json": {
            "current_bid_price": "44.99",
            "is_high_bidder": true
        }
    }
}
```

### Get Closed Auctions
Lists closed auctions.

**Parameters:**
* `startDate`, `endDate`.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_closed_auctions&startDate=2000-01-02&endDate=2015-5-15`

**Example Response (JSON):**
```json
{
  "GetClosedAuctionsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "Auctions": [
      {
        "Domain": "testdomain1.test",
        "AuctionWonStatus": "won"
      }
    ]
  }
}
```

### Get Open Backorder Auctions
Lists open backorder auctions (Deprecated).

**Parameters:**
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_open_backorder_auctions&currency=usd`

**Example Response (JSON):**
```json
{
  "GetOpenBackorderAuctionsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "GetOpenBackorderAuctionsContent": {
        "Auction": {
             "Domain": "domain.com"
        }
    }
  }
}
```

### Get Backorder Auction Details
Gets details of a backorder auction.

**Parameters:**
* `domain`: Domain name.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_backorder_auction_details&domain=example.com&currency=usd`

**Example Response (JSON):**
```json
{
  "GetBackorderAuctionDetailsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "GetBackorderAuctionDetails": {
      "Auction": {
        "Domain": "example.com",
        "BidPrice": "89.99"
      }
    }
  }
}
```

### Place Backorder Auction Bid
Places a bid on a backorder auction.

**Parameters:**
* `domain`: Domain name.
* `bid_amount`: Amount.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=place_backorder_auction_bid&domain=example.com&bid_amount=99.9&currency=usd`

**Example Response (JSON):**
```json
{
   "PlaceBakcorderAuctionBidResponse":{
      "ResponseCode":"0",
      "Status":"success"
   }
}
```

### Get Closed Backorder Auctions
Lists closed backorder auctions.

**Parameters:**
* `startDate`, `endDate`.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_closed_backorder_auctions&startDate=2000-01-02&endDate=2015-5-15`

**Example Response (JSON):**
```json
{
  "GetClosedBackorderAuctionsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "Auctions": [
      {
        "Domain": "testdomain1.test",
        "AuctionWonStatus": "won"
      }
    ]
  }
}
```

### Get Expired Closeout Domains
Lists expired closeout domains.

**Parameters:**
* `currency` (optional).
* `domain` (optional).
* `count_per_page`, `page_index` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_expired_closeout_domains&currency=usd`

**Example Response (JSON):**
```json
{
  "GetExpiredCloseoutDomainsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "CloseoutDomains": [
      {
        "closeoutItem": {
          "domainName": "test.biz",
          "currentPrice": "9.91"
        }
      }
    ]
  }
}
```

### Buy Expired Closeout Domain
Buys a closeout domain.

**Parameters:**
* `domain`: Domain name.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=buy_expired_closeout_domain&currency=USD&domain=domain.com`

**Example Response (JSON):**
```json
{
  "BuyExpiredCloseoutDomainResponse": {
    "ResponseCode": "0",
    "Status": "success"
  }
}
```

### Get Listings
Gets aftermarket listings.

**Parameters:**
* `currency`, `count_per_page`, `page_index`.
* `exclude_pending_sale` (optional).
* `show_other_registrar` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&currency=usd&command=get_listings&count_per_page=20`

**Example Response (JSON):**
```json
{
  "GetListingsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "Listing": [
      {
        "Domain": "domain_name1",
        "Price": "1"
      }
    ]
  }
}
```

### Get Listing Item
Gets details of a listing.

**Parameters:**
* `domain`: Domain name.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_listing_item&currency=usd&domain=domain_name`

**Example Response (JSON):**
```json
{
  "GetListingsItemResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "Listing": {
      "Domain": "domain_name",
      "Price": "0"
    }
  }
}
```

### Buy It Now
Buys a domain from the marketplace.

**Parameters:**
* `domain`: Domain name.
* `currency` (optional).

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=buy_it_now&domain=domain&currency=currency`

**Example Response (JSON):**
```json
{
  "BuyItNowResponse": {
    "ResponseCode": 0,
    "Status": "success"
  }
}
```

### Set For Sale
Lists a domain for sale.

**Parameters:**
* `domains`: Domain name.
* `for_sale_type`: "marketplace", "not_for_sale".
* `listing_type`: "buy_now", "make_offer", "buy_now_and_make_offer".
* `price`, `minimum_offer`, `installment` etc.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_for_sale&domains=test.com&for_sale_type=marketplace&listing_type=buy_now&price=1000`

**Example Response (JSON):**
```json
{
  "SetForSaleResponse": {
    "ResponseCode: ": "0",
    "Status": "Success"
  }
}
```

### Marketplace Confirmations
Sets confirmation actions for 3rd party marketplaces.

**Commands:**
* `set_afternic_confirm_action`
* `set_sedo_confirm_action`

**Parameters:** `domain`, `action` ("confirm..." or "delete...").

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=set_afternic_confirm_action&domain=domain.com&action=confirm_afternic`

**Example Response (JSON):**
```json
{
  "SetAfternicConfirmActionResponse": {
    "ResponseCode": "0",
    "Status": "success"
  }
}
```

### Order List
Lists orders.

**Parameters:** `search_by`, `start_date`, `end_date`, `payment_method`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=order_list&search_by=date_range&start_date=2024/01/01&end_date=2024/01/31`

**Example Response (JSON):**
```json
{
  "OrderListResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "OrderList": [
      {
        "OrderId": "123456",
        "TotalCost": "$8.00"
      }
    ]
  }
}
```

### Get Order Status
Gets status of a specific order.

**Parameters:** `order_id`.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=get_order_status&order_id=0`

**Example Response (JSON):**
```json
{
  "GetOrderStatusResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "OrderStatus": {
      "OrderId": 0,
      "OrderStatus": "Completed"
    }
  }
}
```

### Is Processing
Checks if the system is currently processing a request for the account.

**Parameters:** None.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=is_processing`

**Example Response (JSON):**
```json
{
   "Response":{
      "ResponseCode":"0",
      "ResponseMsg":"no"
   }
}
```

### List Coupons
Lists available coupons.

**Parameters:** `coupon_type` ("registration", "renewal", "transfer").

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=list_coupons&coupon_type=renewal`

**Example Response (JSON):**
```json
{
  "ListCouponsResponse": {
    "ResponseCode": 0,
    "Status": "success",
    "Coupons": [
      {
        "Code": "DOMAINRENEW1",
        "Description": "Domain Renew Coupon"
      }
    ]
  }
}
```

### Transfer Out Domain List
Lists domains transfering out.

**Parameters:** None.

**Example Request:**
`https://api.dynadot.com/api3.json?key=[API_KEY]&command=transfer_domain_list`

**Example Response (JSON):**
```json
{
  "TransferOutDomainListResponse": {
    "ResponseCode": 200,
    "Status": "success",
    "TransferOutDomainList": [
      {
        "orderId": 1,
        "domain": "domain1.com"
      }
    ]
  }
}
```
