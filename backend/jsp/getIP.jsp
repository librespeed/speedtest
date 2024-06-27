<%@ page language="java" contentType="application/json; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.net.URLConnection, 
        java.io.InputStreamReader, 
        java.io.BufferedReader, 
        java.net.URL, 
        java.net.HttpURLConnection, 
        java.util.regex.Pattern"%>
<%
response.setContentType("application/json; charset=utf-8");

String corsParam = request.getParameter("cors");
if (corsParam != null) {
    response.setHeader("Access-Control-Allow-Origin", "*");
    response.setHeader("Access-Control-Allow-Methods", "GET, POST");
}

response.setHeader("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0, s-maxage=0");
response.setHeader("Cache-Control", "post-check=0, pre-check=0");
response.setHeader("Pragma", "no-cache");


// Main logic
String ip = getClientIp( request );
String localIpInfo = getLocalOrPrivateIpInfo(ip);
if (localIpInfo != null) {
    out.println( sendResponse(ip, localIpInfo, null, null) );
    return;
}
out.println( sendResponse(ip, null, null, null) );


%>
<%!

// get the client's ip
public String getClientIp(HttpServletRequest request) {
  String ip = null;
  if (request.getHeader("HTTP_CLIENT_IP") != null && !request.getHeader("HTTP_CLIENT_IP").isEmpty()) {
      ip = request.getHeader("HTTP_CLIENT_IP");
  } else if (request.getHeader("HTTP_X_REAL_IP") != null && !request.getHeader("HTTP_X_REAL_IP").isEmpty()) {
      ip = request.getHeader("HTTP_X_REAL_IP");
  } else if (request.getHeader("HTTP_X_FORWARDED_FOR") != null && !request.getHeader("HTTP_X_FORWARDED_FOR").isEmpty()) {
      ip = request.getHeader("HTTP_X_FORWARDED_FOR");
      ip = ip.split(",")[0]; // hosts are comma-separated, client is first
  } else {
      ip = request.getRemoteAddr();
  }

  if (ip.startsWith("::ffff:")) {
      ip = ip.substring(7);
  }

  return ip;
}


// Function to check if IP is private or local
String getLocalOrPrivateIpInfo(String ip) {
    if ("::1".equals(ip)) {
        return "localhost IPv6 access";
    }
    if (ip.startsWith("fe80:")) {
        return "link-local IPv6 access";
    }
    if (ip.startsWith("127.")) {
        return "localhost IPv4 access";
    }
    if (ip.startsWith("10.") 
    || Pattern.matches("^172\\.(1[6-9]|2\\d|3[01])\\.", ip) 
    || ip.startsWith("192.168.") 
    || ip.startsWith("169.254.")) {
        return "private IPv4 access";
    }
    return null;
}


// Function to send response
String sendResponse(String ip, 
                String ipInfo, 
                String rawIspInfo, 
                String distance) {
    StringBuilder processedString = new StringBuilder(ip);
    if (ipInfo != null && !ipInfo.isEmpty()) {
        processedString.append(" - ").append(ipInfo);
    }
    if (rawIspInfo != null && !rawIspInfo.isEmpty()) {
        String country = rawIspInfo.substring(rawIspInfo.indexOf("\"country\":") + 11, rawIspInfo.indexOf("\",", rawIspInfo.indexOf("\"country\":"))).trim();
        processedString.append(", ").append(country);
    }
    if (distance != null && !distance.isEmpty()) {
        processedString.append(" (").append(distance).append(")");
    }
    return "{\"processedString\": \"" + processedString.toString() + "\", \"rawIspInfo\": " + rawIspInfo + "}";
}






%>
