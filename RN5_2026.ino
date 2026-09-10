#include <WiFi.h>
#include <ModbusIP_ESP8266.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
//+------------------------------------------------------------------+
class RN {
  private:
    double _peso_entrada_oculta[10][10];
    double _peso_oculta_saida[10][10];
    double _bias_oculta[10];
    double _bias_saida[10];
    int _entrada_tamanho;
    int _oculta_tamanho;
    int _saida_tamanho;
    String _func_oculta;
    String _func_saida;

  protected:    
    double funcao_ativacao(const double x, const String func);

  public:
    RN(String func_oculta, String func_saida) : _entrada_tamanho(0), _saida_tamanho(0), _func_oculta(func_oculta), _func_saida(func_saida) {}
    void iniciar(JsonObject w);
    void forward(const double entrada[], double retorno[]);
    int getEntradaTamanho() const { return _entrada_tamanho; }
    int getSaidaTamanho() const { return _saida_tamanho; }
};
//+------------------------------------------------------------------+
void RN::iniciar(JsonObject w) {
  if (!w["peso_entrada_oculta"].is<JsonArray>() ||
      !w["bias_oculta"].is<JsonArray>() ||
      !w["peso_oculta_saida"].is<JsonArray>() ||
      !w["bias_saida"].is<JsonArray>()) {
    
    _entrada_tamanho = 0;
    _oculta_tamanho = 0;
    _saida_tamanho = 0;
    return;
  }

  JsonArray pesoEO = w["peso_entrada_oculta"];
  JsonArray biasO  = w["bias_oculta"];
  JsonArray pesoOS = w["peso_oculta_saida"];
  JsonArray biasS  = w["bias_saida"];

  if (pesoEO.size() == 0 || !pesoEO[0].is<JsonArray>()) {
    _entrada_tamanho = 0;
    _oculta_tamanho = 0;
    _saida_tamanho = 0;
    return;
  }

  _entrada_tamanho = min((int)pesoEO.size(), 10);
  _oculta_tamanho  = min((int)pesoEO[0].size(), 10);
  _saida_tamanho   = min((int)biasS.size(), 10);

  for (int j = 0; j < _oculta_tamanho; j++) {
    double biasOculta = biasO[j].as<double>();
    Serial.println("Bias Oculta ["+(String)j+"]:"+(String)biasOculta);    
    _bias_oculta[j] = biasOculta;
    
    for (int i = 0; i < _entrada_tamanho; i++) {
       double entradaOculta = pesoEO[i][j].as<double>();
       Serial.println("Entrada-oculta ["+(String)i+"]["+(String)j+"]:"+(String)entradaOculta);   
      _peso_entrada_oculta[i][j] = entradaOculta;
    }
  }

  for (int k = 0; k < _saida_tamanho; k++) {
    double biasSaida = biasS[k].as<double>();
    Serial.println("Bias Saída ["+(String)k+"]:"+(String)biasSaida); 
    _bias_saida[k] = biasSaida;
    
    for (int j = 0; j < _oculta_tamanho; j++) {
       double ocultaSaida = pesoOS[j][k].as<double>();
       Serial.println("Oculta-saída ["+(String)j+"]["+(String)k+"]:"+(String)ocultaSaida); 
      _peso_oculta_saida[j][k] = ocultaSaida;
    }
  }
}
//+------------------------------------------------------------------+
double RN::funcao_ativacao(const double x, const String func){
  if(func == "sigmoid")
    return (1 / (1 + exp(-x)));
  else
    if(func == "tanh")
       return tanh(x);
    else
       return x;
}
//+------------------------------------------------------------------+
void RN::forward(const double entrada[], double retorno[]){
  float oculta_funcao[10];
  
  for(int j=0; j < _oculta_tamanho; j++){
    double total = _bias_oculta[j];    
    for(int i=0; i < _entrada_tamanho; i++) {
      total += entrada[i] * _peso_entrada_oculta[i][j];    
    }
    oculta_funcao[j] = funcao_ativacao(total, _func_oculta);
  }
  
  for(int j=0; j < _saida_tamanho; j++){
    double total = _bias_saida[j];    
    for(int i=0; i < _oculta_tamanho; i++) {
      total += oculta_funcao[i] * _peso_oculta_saida[i][j];
    }
    retorno[j] = funcao_ativacao(total, _func_saida);
  }
}
//+------------------------------------------------------------------+
  const char* ssid = "AT";
  const char* password = "Drithi1607";
  const uint16_t modbusPort = 502;
  IPAddress factoryIP(192, 168, 1, 16);
  const String url = "http://192.168.1.16/industrial/funcoes/planta.php";
  ModbusIP mb;
  HTTPClient http;

  RN manipuladorB("tanh", "sigmoid");
  const int led = 2;
  unsigned long limitRegistros = 200;
  
  unsigned long contadorEscritas = 0;
  unsigned long somaTempos = 0;
  unsigned long menorTempo = ULONG_MAX;
  unsigned long maiorTempo = 0;
  unsigned long contadorLoop = 0;
  unsigned long somaTempoLoop = 0;
  unsigned long menorTempoLoop = ULONG_MAX;
  unsigned long maiorTempoLoop = 0;
//+------------------------------------------------------------------+
void conectarWiFi() {
  digitalWrite(led, HIGH);
  Serial.println("Conectando ao Wi-Fi");
  WiFi.begin(ssid, password);
  int cnt = 0;

  while (WiFi.status() != WL_CONNECTED && cnt < 100) {
    delay(500);
    cnt++;
    Serial.println("Tentativa de conexão: "+(String)cnt);
  }

  if (WiFi.status() == WL_CONNECTED) {
    digitalWrite(led, LOW);
    Serial.print("ESP conectado ao Wi-Fi com IP:");
    Serial.println(WiFi.localIP());
  } else {
    digitalWrite(led, HIGH);
    Serial.println("Falha ao conectar no wi-fi");
  }
}
//+------------------------------------------------------------------+
void conectarModbus() {
  Serial.println("Tentando conectar no Factory I/O...");
  mb.connect(factoryIP, modbusPort);
  delay(1000);

  if (mb.isConnected(factoryIP)) {
    Serial.println("TCP/Modbus conectado.");
  } else {
    Serial.println("Falha ao conectar TCP/Modbus.");     
  }
}
//+------------------------------------------------------------------+
void conectarHTTP(const double entradas[4], const double saidas[4]) {
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");

  int tamEnt = manipuladorB.getEntradaTamanho();
  int tamSai = manipuladorB.getSaidaTamanho();
  JsonDocument docEnvio;

  if(tamEnt > 0) {
    JsonArray entB = docEnvio["maquinas"]["_expedicaoB"].to<JsonArray>();

    for(int i=0; i<tamEnt; i++) {
      entB.add(entradas[i]);
    }
  }

  if(tamSai > 0) {
    JsonArray saiB = docEnvio["maquinas"]["expedicaoB"].to<JsonArray>();

    for(int i=0; i<tamSai; i++) {
      saiB.add(saidas[i]);
    }
  }

  String jsonString;
  serializeJson(docEnvio, jsonString);
  Serial.print("Enviando JSON:");
  Serial.println(jsonString);
  int httpCode = http.POST(jsonString);

  if (httpCode > 0) {
    String payload = http.getString();
    Serial.print("Resposta JSON:");
    Serial.println(payload);

    JsonDocument doc;
    DeserializationError error = deserializeJson(doc, payload);

    if (error) {
      Serial.print("Erro ao ler JSON: ");
      Serial.println(error.c_str());
    } else {
      manipuladorB.iniciar(doc["dados"]["expedicao"]);
    }
  } else {
    Serial.print("Erro HTTP: ");
    Serial.println(httpCode);
  }

  http.end();
}
//+------------------------------------------------------------------+
void setup() {
  Serial.begin(115200);
  pinMode(led, OUTPUT);
  digitalWrite(led, LOW);
  
  conectarWiFi();
  double arr_vazio[4] = {0};
  conectarHTTP(arr_vazio, arr_vazio);
  conectarModbus();
  
  delay(500);
  Serial.println("ESP32 pronto para enviar comandos Modbus ao Factory I/O");
}
//+------------------------------------------------------------------+
void loop() {
  unsigned long inicioLoop = millis();
  digitalWrite(led, HIGH);
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi desconectado...");
    conectarWiFi();
  } 
  
  mb.task();
  bool dig[2];
  uint16_t ana[2];
  uint16_t transIsts = mb.readIsts(factoryIP, 4, dig, 2);
  while (mb.isTransaction(transIsts)) mb.task();

  if (transIsts != 0) {
    uint16_t transIreg = mb.readIreg(factoryIP, 3, ana, 2);
    while (mb.isTransaction(transIreg)) mb.task();

    if (transIreg != 0) {
      double saida[4] = {0};
      double entrada[4] = {dig[0], dig[1], (double)ana[0]/1000, (double)ana[1]/1000};
      manipuladorB.forward(entrada, saida);

      Serial.print("ExpedicaoB: [");
      for (int j = 0; j < 4; j++) {
        Serial.print(entrada[j], 6);
        if (j < 3) Serial.print(",");
      }
      Serial.print("] -> [");
      for (int j = 0; j < 4; j++) {
        Serial.print(saida[j], 10);
        if (j < 3) Serial.print(",");
      }
      Serial.println("]");

      bool coil = (saida[0] > 0.950) ? true : false;
      uint16_t reg[3] = {(uint16_t)(saida[1]*1000), (uint16_t)(saida[2]*1000), (uint16_t)(saida[3]*1000)};
      
      unsigned long inicioEscrita = millis();
      uint16_t transCoil = mb.writeCoil(factoryIP, 7, coil);
      uint16_t transHreg = mb.writeHreg(factoryIP, 4, reg, 3);
      while (mb.isTransaction(transCoil) || mb.isTransaction(transHreg)) mb.task();
      unsigned long duracaoEscrita = millis() - inicioEscrita;

      Serial.print(contadorEscritas);
      Serial.print(" -> Tempo de escrita Modbus (ms): ");
      Serial.println(duracaoEscrita);

      if (contadorEscritas < limitRegistros) {
        somaTempos += duracaoEscrita;
        if (duracaoEscrita < menorTempo) menorTempo = duracaoEscrita;
        if (duracaoEscrita > maiorTempo) maiorTempo = duracaoEscrita;
        contadorEscritas++;

        if (contadorEscritas == limitRegistros) {
          Serial.println("===== Estatística das primeiras escritas Modbus =====");
          Serial.print("Média (ms): ");
          Serial.println((double)somaTempos / contadorEscritas, 8);
          Serial.print("Menor tempo (ms): ");
          Serial.println(menorTempo);
          Serial.print("Maior tempo (ms): ");
          Serial.println(maiorTempo);
          Serial.println("============================================================");
        }
      }

      if (WiFi.status() == WL_CONNECTED) conectarHTTP(entrada, saida);
    } else {
      Serial.println("Falha em ler as entradas analógicas");
      conectarModbus();
    }
  } 
  else {
      Serial.println("Falha em ler as entradas digitais");
      conectarModbus();
  }  

  digitalWrite(led, LOW);
  delay(10);

  unsigned long duracaoLoop = millis() - inicioLoop;
  if (contadorLoop < limitRegistros) {
    somaTempoLoop += duracaoLoop;
    if (duracaoLoop < menorTempoLoop) menorTempoLoop = duracaoLoop;
    if (duracaoLoop > maiorTempoLoop) maiorTempoLoop = duracaoLoop;
    contadorLoop++;

    if (contadorLoop == limitRegistros) {
      Serial.println("===== Estatística das primeiras execuções do loop =====");
      Serial.print("Média (ms): ");
      Serial.println((double)somaTempoLoop / contadorLoop, 8);
      Serial.print("Menor tempo (ms): ");
      Serial.println(menorTempoLoop);
      Serial.print("Maior tempo (ms): ");
      Serial.println(maiorTempoLoop);
      Serial.println("==============================================================");
    }
  }
}
//+------------------------------------------------------------------+
